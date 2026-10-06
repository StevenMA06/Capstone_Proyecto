<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InstallationService
{
    public function assignments(User $user)
    {
        $query = DB::table('pedidos_instalacion');
        if (! $user->hasPermission('technician.supervise')) {
            $user->empleado_id ? $query->where('tecnico_id', $user->empleado_id) : $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function find(User $user, int $id, bool $lock = false): object
    {
        $query = $this->assignments($user)->where('pedido_id', $id);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);

        return $record;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function running(object $installation): object
    {
        $execution = DB::table('ejecuciones_instalacion')->where('pedido_id', $installation->pedido_id)->first();
        if ($installation->estado !== 'EN_PROCESO' || ! $execution?->inicio_en || $execution->estado !== 'EN_PROCESO') {
            $this->fail('instalacion', 'Esta acción requiere una instalación iniciada y en proceso.');
        }

        return $execution;
    }

    public function start(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $record = $this->find($user, $id, true);
            if ($record->estado === 'EN_PROCESO' && DB::table('ejecuciones_instalacion')->where('pedido_id', $id)->whereNotNull('inicio_en')->exists()) {
                $this->running($record);

                return;
            }
            if (! in_array($record->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'], true)) {
                $this->fail('instalacion', 'No se puede iniciar una instalación completada o cancelada.');
            }
            if (! $record->tecnico_id || ! Employee::eligible()->whereKey($record->tecnico_id)->exists()) {
                $this->fail('instalacion', 'La instalación debe tener un técnico activo asignado.');
            }
            $execution = DB::table('ejecuciones_instalacion')->where('pedido_id', $id)->first();
            if ($execution && ($execution->estado !== 'PENDIENTE' || $execution->inicio_en || $execution->fin_en)) {
                $this->fail('instalacion', 'La ejecución ya fue iniciada. Revisa su estado antes de continuar.');
            }
            $time = now('America/Lima');
            DB::table('ejecuciones_instalacion')->updateOrInsert(['pedido_id' => $id], [
                'tecnico_id' => $record->tecnico_id, 'inicio_en' => $time, 'estado' => 'EN_PROCESO', 'actualizado_en' => $time,
            ]);
            DB::table('pedidos_instalacion')->where('pedido_id', $id)->update(['estado' => 'EN_PROCESO', 'actualizado_en' => $time]);
        }, 3);
    }

    public function equipment(User $user, int $id, int $equipmentId): void
    {
        DB::transaction(function () use ($user, $id, $equipmentId) {
            $this->running($this->find($user, $id, true));
            $equipment = DB::table('equipos')->where('equipo_id', $equipmentId)->lockForUpdate()->first();
            if (! $equipment) {
                $this->fail('equipo_id', 'El equipo seleccionado no existe.');
            }
            $assignment = DB::table('pedido_equipos')->where('equipo_id', $equipmentId)
                ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])->whereNull('fecha_devolucion')->first();
            if ($assignment && (int) $assignment->pedido_id === $id && $assignment->estado_asignacion === 'UTILIZADO') {
                return;
            }
            if (($assignment && (int) $assignment->pedido_id !== $id) || (! $assignment && $equipment->estado !== 'DISPONIBLE')
                || ! in_array($equipment->estado, ['DISPONIBLE', 'ASIGNADO'], true)
                || in_array($equipment->condicion, ['MALO', 'MALOGRADO'], true)) {
                $this->fail('equipo_id', 'El equipo no está disponible para esta instalación.');
            }
            if ($assignment) {
                DB::table('pedido_equipos')->where('pedido_equipo_id', $assignment->pedido_equipo_id)->update(['estado_asignacion' => 'UTILIZADO']);
            } else {
                DB::table('pedido_equipos')->insert(['pedido_id' => $id, 'equipo_id' => $equipmentId,
                    'asignado_por' => $user->getKey(), 'estado_asignacion' => 'UTILIZADO']);
                DB::table('movimientos_inventario')->insert(['tipo_movimiento' => 'ASIGNACION', 'equipo_id' => $equipmentId,
                    'pedido_id' => $id, 'cantidad' => 1, 'responsable_id' => $user->getKey(), 'observacion' => 'Equipo utilizado en instalación']);
            }
            DB::table('equipos')->where('equipo_id', $equipmentId)->update(['estado' => 'ASIGNADO']);
        }, 3);
    }

    // The form submits the cumulative total. Repeating the same request never consumes stock twice.
    public function material(User $user, int $id, int $materialId, string $total): void
    {
        DB::transaction(function () use ($user, $id, $materialId, $total) {
            $this->running($this->find($user, $id, true));
            $material = DB::table('materiales')->where('material_id', $materialId)->lockForUpdate()->first();
            if (! $material || $material->estado !== 'ACTIVO') {
                $this->fail('material_id', 'Selecciona un material activo.');
            }
            $assignment = DB::table('pedido_materiales')->where('pedido_id', $id)->where('material_id', $materialId)->first();
            // Quantities are represented as thousandths to avoid floating-point subtraction errors.
            $units = fn ($value) => (int) round((float) $value * 1000);
            $requested = $units($total);
            $previous = $units($assignment?->cantidad_utilizada ?? 0);
            $delta = $requested - $previous;
            if ($requested <= 0 || $delta < 0) {
                $this->fail('cantidad_utilizada', 'El total debe ser positivo y no puede ser menor que el consumo ya registrado.');
            }
            if ($delta === 0) {
                return;
            }
            $stock = $units($material->stock_actual);
            if ($delta > $stock) {
                // Obtener la unidad correcta: usar unidad_empaque si existe, sino unidad_base
                $unit = $material->unidad_empaque ?? $material->unidad_base;
                $this->fail('cantidad_utilizada', 'Stock insuficiente para registrar el consumo adicional. Disponible: '.$material->stock_actual.' '.$unit.'.');
            }
            $format = fn ($value) => number_format($value / 1000, 3, '.', '');
            DB::table('materiales')->where('material_id', $materialId)->update(['stock_actual' => $format($stock - $delta)]);
            $values = ['cantidad_utilizada' => $format($requested),
                'cantidad_asignada' => $format(max($units($assignment?->cantidad_asignada ?? 0), $requested + $units($assignment?->cantidad_devuelta ?? 0)))];
            if ($assignment) {
                DB::table('pedido_materiales')->where('pedido_material_id', $assignment->pedido_material_id)->update($values);
            } else {
                DB::table('pedido_materiales')->insert($values + ['pedido_id' => $id, 'material_id' => $materialId, 'asignado_por' => $user->getKey()]);
            }
            DB::table('movimientos_inventario')->insert(['tipo_movimiento' => 'CONSUMO', 'material_id' => $materialId,
                'pedido_id' => $id, 'cantidad' => $format($delta), 'cantidad_anterior' => $format($stock),
                'cantidad_posterior' => $format($stock - $delta), 'responsable_id' => $user->getKey(),
                'observacion' => 'Consumo de material en instalación']);
        }, 3);
    }

    public function observation(User $user, int $id, ?string $text): void
    {
        DB::transaction(function () use ($user, $id, $text) {
            $execution = $this->running($this->find($user, $id, true));
            DB::table('ejecuciones_instalacion')->where('ejecucion_id', $execution->ejecucion_id)
                ->update(['observacion_tecnica' => $text, 'actualizado_en' => now('America/Lima')]);
        });
    }

    public function evidence(User $user, int $id, UploadedFile $file, ?string $description, bool $contract = false): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($user, $id, $file, $description, $contract, &$path) {
                $record = $this->find($user, $id, true);
                $execution = $this->running($record);
                $path = $file->store($contract ? 'installation-contracts' : 'installation-evidence', 'local');
                if (! $path) {
                    $this->fail('evidence', 'No se pudo guardar el archivo. Inténtalo nuevamente.');
                }
                $values = ['ruta_archivo' => $path, 'nombre_archivo' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(), 'subido_por' => $user->getKey(), 'subido_en' => now('America/Lima')];
                if ($contract) {
                    DB::table('evidencias_contrato')->insert($values + ['contrato_id' => $record->contrato_id,
                        'tipo' => 'CONTRATO_FIRMADO', 'observacion' => $description]);
                } else {
                    DB::table('evidencias_instalacion')->insert($values + ['pedido_id' => $id, 'ejecucion_id' => $execution->ejecucion_id,
                        'tipo' => 'FOTOGRAFIA_INSTALACION', 'descripcion' => $description]);
                }
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    public function finish(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $record = $this->find($user, $id, true);
            if ($record->estado === 'COMPLETADO') {
                return;
            }
            $execution = $this->running($record);
            $time = now('America/Lima');
            DB::table('ejecuciones_instalacion')->where('ejecucion_id', $execution->ejecucion_id)
                ->update(['estado' => 'COMPLETADA', 'fin_en' => $time, 'actualizado_en' => $time]);
            DB::table('pedidos_instalacion')->where('pedido_id', $id)->update(['estado' => 'COMPLETADO', 'actualizado_en' => $time]);
        }, 3);
    }
}
