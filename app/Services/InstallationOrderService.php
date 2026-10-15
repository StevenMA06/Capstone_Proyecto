<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstallationOrderService
{
    public const REPROGRAM_REASONS = [
        'SOLICITUD_CLIENTE' => 'Solicitud del cliente',
        'DISPONIBILIDAD_TECNICO' => 'Disponibilidad del técnico',
        'FALTA_RECURSOS' => 'Falta de equipo o material',
        'CLIMA' => 'Condiciones climáticas',
        'IMPREVISTO' => 'Imprevisto operativo',
        'OTRO' => 'Otro motivo',
    ];

    /** Create the installation order when a contract is registered. */
    public function createForContract(object $contract, int $actorId): int
    {
        $existing = DB::table('pedidos_instalacion')->where('contrato_id', $contract->contrato_id)->value('pedido_id');
        if ($existing) return (int) $existing;

        return DB::table('pedidos_instalacion')->insertGetId([
            'codigo' => 'INS-'.Str::upper(Str::random(12)),
            'contrato_id' => $contract->contrato_id,
            'cliente_id' => $contract->cliente_id,
            'estado' => 'PENDIENTE',
            'fase_flujo' => 'PENDIENTE_COORDINACION',
            'estado_documentacion' => 'PENDIENTE_INSTALACION',
            'creado_por' => $actorId,
            'fecha_solicitud' => now('America/Lima'),
        ]);
    }

    /** Link any existing legacy request to its formal client and contract. */
    public function linkFormalContract(object $contract, int $actorId): ?int
    {
        $document = DB::table('clientes')->where('cliente_id', $contract->cliente_id)->value('numero_documento');
        $order = $document ? DB::table('pedidos_instalacion')->whereNull('contrato_id')->where('documento_cliente', $document)
            ->whereIn('estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO', 'COMPLETADO'])
            ->orderByRaw('CASE WHEN cliente_id = ? THEN 0 ELSE 1 END', [$contract->cliente_id])
            ->orderByRaw('CASE WHEN estado IN (\'EN_PROCESO\', \'COMPLETADO\') THEN 0 ELSE 1 END')
            ->orderByDesc('pedido_id')->lockForUpdate()->first() : null;
        if (! $order) {
            if (! in_array($contract->estado ?? null, ['PENDIENTE', 'ACTIVO'], true)) return null;
            $orderId = $this->createForContract($contract, $actorId);
            if ($contract->firma_revision === 'VERIFICADA') {
                DB::afterCommit(fn () => app(InstallationDriveService::class)->syncVerifiedContract($orderId));
            }
            return $orderId;
        }

        DB::table('pedidos_instalacion')->where('pedido_id', $order->pedido_id)->update([
            'cliente_id' => $contract->cliente_id,
            'contrato_id' => $contract->contrato_id,
            'estado_documentacion' => $contract->firma_revision === 'VERIFICADA' ? 'PENDIENTE_VERIFICACION' : 'PENDIENTE_FIRMA',
            'actualizado_en' => now('America/Lima'),
        ]);
        if (DB::getSchemaBuilder()->hasTable('pedido_historial_eventos')) {
            DB::table('pedido_historial_eventos')->insert([
                'pedido_id' => $order->pedido_id, 'usuario_id' => $actorId, 'tipo' => 'CONTRATO_VINCULADO',
                'motivo' => 'Contrato formal asociado a la instalación.',
                'contexto' => json_encode(['contrato_id' => $contract->contrato_id]), 'registrado_en' => now('America/Lima'),
            ]);
        }
        if ($contract->firma_revision === 'VERIFICADA') {
            DB::afterCommit(fn () => app(InstallationDriveService::class)->syncVerifiedContract((int) $order->pedido_id));
        }
        return (int) $order->pedido_id;
    }

    public function coordinate(User $actor, int $id, array $data, ?UploadedFile $evidence = null): bool
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, $evidence, &$storedPath): bool {
            $order = $this->editable($id);
            $this->requireActiveContract($order);
            $alreadyScheduled = (bool) $order->fecha_programada;
            if ($alreadyScheduled) {
                if (! isset(self::REPROGRAM_REASONS[$data['motivo_codigo'] ?? ''])) {
                    $this->fail('motivo_codigo', 'Selecciona el motivo de la reprogramación.');
                }
                if ($data['motivo_codigo'] === 'OTRO' && blank($data['motivo_reprogramacion'] ?? null)) {
                    $this->fail('motivo_reprogramacion', 'Describe el otro motivo de la reprogramación.');
                }
                if (! $evidence) {
                    $this->fail('evidencia_acuerdo', 'Adjunta una evidencia del acuerdo antes de enviar la solicitud a Gerencia.');
                }
                if (Carbon::parse($order->fecha_programada)->equalTo(Carbon::parse($data['fecha_programada']))) {
                    $this->fail('fecha_programada', 'La nueva fecha y hora deben ser distintas de las actuales.');
                }
                if (DB::table('pedido_reprogramaciones')->where('pedido_id', $id)->where('estado', 'SOLICITADA')->exists()) {
                    $this->fail('pedido', 'Ya existe una solicitud pendiente de Gerencia para este pedido.');
                }
                $storedPath = $evidence->store('installation-rescheduling', 'local');
                if (! $storedPath) {
                    $this->fail('evidencia_acuerdo', 'No se pudo guardar la evidencia. Inténtalo de nuevo.');
                }
                DB::table('pedido_reprogramaciones')->insert([
                    'pedido_id' => $id,
                    'fecha_anterior' => $order->fecha_programada,
                    'fecha_nueva' => $data['fecha_programada'],
                    'franja_anterior' => $order->franja_horaria,
                    'franja_nueva' => $data['franja_horaria'],
                    'canal_coordinacion' => $data['canal_coordinacion'],
                    'detalle_coordinacion' => $data['detalle_coordinacion'] ?? null,
                    'motivo_codigo' => $data['motivo_codigo'],
                    'motivo_detalle' => trim($data['motivo_reprogramacion'] ?? ''),
                    'evidencia_ruta' => $storedPath,
                    'evidencia_nombre' => mb_substr(basename(str_replace('\\', '/', $evidence->getClientOriginalName())), 0, 255),
                    'evidencia_sha256' => hash_file('sha256', $evidence->getRealPath()),
                    'evidencia_tipo' => $evidence->getMimeType(),
                    'solicitado_por' => $actor->getKey(),
                    'solicitado_en' => now('America/Lima'),
                    'estado' => 'SOLICITADA',
                ]);

                return true;
            }
            DB::table('pedidos_instalacion')->where('pedido_id', $id)->update([
                'fecha_programada' => $data['fecha_programada'],
                'fecha_original' => $order->fecha_original ?? $data['fecha_programada'],
                'franja_horaria' => $data['franja_horaria'],
                'canal_coordinacion' => $data['canal_coordinacion'],
                'detalle_coordinacion' => $data['detalle_coordinacion'] ?? null,
                'coordinado_por' => $actor->getKey(),
                'coordinado_en' => now('America/Lima'),
                'fase_flujo' => $order->tecnico_id ? 'ESPERANDO_CONFIRMACION_TECNICO' : 'PENDIENTE_ASIGNACION',
                'estado' => 'PENDIENTE',
                'confirmado_por_tecnico_en' => $order->tecnico_id ? null : $order->confirmado_por_tecnico_en,
                'notificado_en' => $order->tecnico_id ? null : $order->notificado_en,
                'motivo_reprogramacion' => null,
                'actualizado_en' => now('America/Lima'),
            ]);
            if ($order->tecnico_id) DB::afterCommit(fn () => $this->notifyTechnician($id, $order->tecnico_id, 'reprogrammed'));

            return false;
            }, 3);
        } catch (\Throwable $error) {
            if ($storedPath) Storage::disk('local')->delete($storedPath);
            throw $error;
        }
    }

    public function resolveReprogramming(User $actor, int $orderId, int $requestId, bool $approve, ?string $observation = null): void
    {
        abort_unless($actor->hasPermission('orders.approve'), 403);
        DB::transaction(function () use ($actor, $orderId, $requestId, $approve, $observation): void {
            $order = DB::table('pedidos_instalacion')->where('pedido_id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404);
            $change = DB::table('pedido_reprogramaciones')->where('pedido_id', $orderId)
                ->where('reprogramacion_id', $requestId)->lockForUpdate()->first();
            abort_unless($change, 404);
            if ($change->estado !== 'SOLICITADA') {
                $this->fail('reprogramacion', 'Esta solicitud ya fue resuelta.');
            }
            if ((int) $change->solicitado_por === (int) $actor->getKey()) {
                $this->fail('reprogramacion', 'No puedes aprobar ni rechazar una solicitud que registraste tú.');
            }
            if ($approve) {
                $this->editable($orderId);
                $this->requireActiveContract($order);
                if (! $this->validEvidence($change)) {
                    $this->fail('evidencia_acuerdo', 'La evidencia falta o cambió. Rechaza esta solicitud y registra una nueva.');
                }
                if (! $order->fecha_programada || ! Carbon::parse($order->fecha_programada)->equalTo(Carbon::parse($change->fecha_anterior))
                    || $order->franja_horaria !== $change->franja_anterior) {
                    $this->fail('reprogramacion', 'La programación vigente cambió. Rechaza esta solicitud y registra una nueva.');
                }
                if (! Carbon::parse($change->fecha_nueva, 'America/Lima')->greaterThan(now('America/Lima'))) {
                    $this->fail('fecha_programada', 'La fecha solicitada ya pasó. Registra una nueva solicitud.');
                }
                foreach (array_filter([(int) $order->tecnico_id, (int) $order->tecnico_secundario_id]) as $employeeId) {
                    Employee::lockForUpdate()->find($employeeId);
                    if ($this->hasTechnicianScheduleConflict($employeeId, $orderId, $change->fecha_nueva, $change->franja_nueva)) {
                        $this->fail('reprogramacion', 'Uno de los técnicos asignados ya tiene otra instalación ese día en esa franja. Rechaza este cambio o coordina otra franja.');
                    }
                }
                DB::table('pedidos_instalacion')->where('pedido_id', $orderId)->update([
                    'fecha_programada' => $change->fecha_nueva,
                    'fecha_original' => $order->fecha_original ?? $change->fecha_anterior,
                    'franja_horaria' => $change->franja_nueva,
                    'canal_coordinacion' => $change->canal_coordinacion,
                    'detalle_coordinacion' => $change->detalle_coordinacion,
                    'coordinado_por' => $change->solicitado_por,
                    'coordinado_en' => $change->solicitado_en,
                    'fase_flujo' => $order->tecnico_id ? 'ESPERANDO_CONFIRMACION_TECNICO' : 'PENDIENTE_ASIGNACION',
                    'estado' => 'REPROGRAMADO',
                    'confirmado_por_tecnico_en' => null,
                    'notificado_en' => null,
                    'motivo_reprogramacion' => $change->motivo_detalle,
                    'actualizado_en' => now('America/Lima'),
                ]);
            } elseif (blank($observation)) {
                $this->fail('decision_observacion', 'Indica por qué se rechaza la solicitud.');
            }
            DB::table('pedido_reprogramaciones')->where('reprogramacion_id', $requestId)->update([
                'estado' => $approve ? 'APROBADA' : 'RECHAZADA',
                'resuelto_por' => $actor->getKey(),
                'resuelto_en' => now('America/Lima'),
                'decision_observacion' => $observation,
            ]);
            if ($approve) {
                DB::afterCommit(function () use ($orderId, $order): void {
                    if ($order->tecnico_id) $this->notifyTechnician($orderId, $order->tecnico_id, 'reprogrammed');
                    if ($order->tecnico_secundario_id) $this->notifyTechnician($orderId, $order->tecnico_secundario_id, 'reprogrammed');
                    $this->notifyClient($orderId, $order->cliente_id);
                });
            }
        }, 3);
    }

    public function assign(User $actor, int $id, int $technicianId, ?string $reason = null, ?string $legacyDate = null, ?int $secondTechnicianId = null): void
    {
        DB::transaction(function () use ($actor, $id, $technicianId, $reason, $legacyDate, $secondTechnicianId): void {
            $order = $this->editable($id);
            $this->requireActiveContract($order);
            if (DB::table('pedido_reprogramaciones')->where('pedido_id', $id)->where('estado', 'SOLICITADA')->exists()) {
                $this->fail('pedido', 'Espera la decisión de Gerencia antes de asignar al técnico.');
            }
            if ($order->fase_flujo && (! $order->coordinado_en || ! $order->fecha_programada || ! $order->franja_horaria)) {
                $this->fail('pedido', 'Primero registra la fecha y franja confirmadas con el cliente.');
            }
            if (! $order->fase_flujo && ! $legacyDate) {
                $this->fail('fecha_programada', 'Indica la nueva fecha para este pedido anterior.');
            }
            if (! $order->tecnico_id
                && ! DB::table('pedido_equipos')->where('pedido_id', $id)
                    ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])->whereNull('fecha_devolucion')->exists()
                && ! DB::table('equipos as e')->where('e.estado', 'DISPONIBLE')
                    ->whereNotIn('e.condicion', ['MALO', 'MALOGRADO'])
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pedido_solicitudes_recurso as s')
                        ->whereIn('s.estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])
                        ->where(fn ($request) => $request->whereColumn('s.equipo_id', 'e.equipo_id')
                            ->orWhereRaw('s.equipo_tipo_solicitado COLLATE utf8mb4_unicode_ci = e.tipo COLLATE utf8mb4_unicode_ci')))
                    ->exists()) {
                $this->fail('equipo_id', 'No hay equipos disponibles. Espera el reabastecimiento de stock antes de asignar un técnico.');
            }
            if (! $order->tecnico_id && (! DB::table('pedido_equipos')->where('pedido_id', $id)
                ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])->whereNull('fecha_devolucion')->exists()
                || DB::table('pedido_solicitudes_recurso')->where('pedido_id', $id)
                    ->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])->exists())) {
                $this->fail('pedido', 'Primero solicita los recursos y espera a que Almacén los prepare antes de asignar al técnico.');
            }
            if ($order->tecnico_id && blank($reason)) {
                $this->fail('motivo_reprogramacion', 'Justifica el cambio de técnico.');
            }
            $technician = Employee::lockForUpdate()->find($technicianId);
            if (! $technician?->isEligible() || $technician->cargo !== 'Técnico de Campo') {
                $this->fail('tecnico_id', 'Selecciona un técnico de campo activo.');
            }
            $secondTechnician = null;
            if ($secondTechnicianId) {
                if ($secondTechnicianId === $technicianId) $this->fail('tecnico_secundario_id', 'Selecciona dos técnicos distintos.');
                $secondTechnician = Employee::lockForUpdate()->find($secondTechnicianId);
                if (! $secondTechnician?->isEligible() || $secondTechnician->cargo !== 'Técnico de Campo') {
                    $this->fail('tecnico_secundario_id', 'Selecciona un segundo técnico de campo activo.');
                }
            }
            $scheduledAt = $order->fase_flujo ? $order->fecha_programada : $legacyDate;
            if ($this->hasTechnicianScheduleConflict($technicianId, $id, $scheduledAt, $order->franja_horaria)) {
                $this->fail('tecnico_id', 'Este técnico ya tiene otra instalación ese día en esa franja. Elige otra franja o técnico.');
            }
            if ($secondTechnicianId && $this->hasTechnicianScheduleConflict($secondTechnicianId, $id, $scheduledAt, $order->franja_horaria)) {
                $this->fail('tecnico_secundario_id', 'El segundo técnico ya tiene otra instalación ese día en esa franja. Elige otra franja o técnico.');
            }
            $isRescheduledAssignment = $order->estado === 'REPROGRAMADO'
                || (! $order->fase_flujo && $order->fecha_programada
                    && ! Carbon::parse($order->fecha_programada)->equalTo(Carbon::parse($legacyDate)));
            $when = now('America/Lima');
            DB::table('pedidos_instalacion')->where('pedido_id', $id)->update([
                'tecnico_id' => $technicianId,
                'tecnico_secundario_id' => $secondTechnicianId,
                'fecha_programada' => $order->fase_flujo ? $order->fecha_programada : $legacyDate,
                'fecha_original' => $order->fecha_original ?? ($order->fase_flujo ? $order->fecha_programada : $legacyDate),
                'asignado_por' => $actor->getKey(),
                'asignado_en' => $when,
                'confirmado_por_tecnico_en' => null,
                'confirmado_por_tecnico_secundario_en' => null,
                'notificado_en' => null,
                'estado' => $order->estado === 'REPROGRAMADO' || (! $order->fase_flujo && $order->fecha_programada && ! Carbon::parse($order->fecha_programada)->equalTo(Carbon::parse($legacyDate)))
                    ? 'REPROGRAMADO' : 'PROGRAMADO',
                'fase_flujo' => $order->fase_flujo ? 'ESPERANDO_CONFIRMACION_TECNICO' : null,
                'motivo_reprogramacion' => $reason ?? $order->motivo_reprogramacion,
                'actualizado_en' => $when,
            ]);
            $notificationType = $isRescheduledAssignment ? 'reprogrammed_assignment' : 'assignment';
            DB::afterCommit(function () use ($id, $technicianId, $secondTechnicianId, $notificationType): void {
                $this->notifyTechnician($id, $technicianId, $notificationType);
                if ($secondTechnicianId) $this->notifyTechnician($id, $secondTechnicianId, $notificationType);
            });
        }, 3);
    }

    public function confirm(User $technician, int $id): void
    {
        DB::transaction(function () use ($technician, $id): void {
            $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->lockForUpdate()->first();
            abort_unless($order && in_array((int) $technician->empleado_id, [(int) $order->tecnico_id, (int) $order->tecnico_secundario_id], true), 404);
            if ($order->fase_flujo === 'CONFIRMADO') return;
            if ($order->fase_flujo !== 'ESPERANDO_CONFIRMACION_TECNICO') {
                $this->fail('pedido', 'Esta asignación no está pendiente de confirmación.');
            }
            $column = (int) $order->tecnico_id === (int) $technician->empleado_id
                ? 'confirmado_por_tecnico_en' : 'confirmado_por_tecnico_secundario_en';
            $confirmedPrimary = $column === 'confirmado_por_tecnico_en' || $order->confirmado_por_tecnico_en;
            $confirmedSecondary = ! $order->tecnico_secundario_id || $column === 'confirmado_por_tecnico_secundario_en' || $order->confirmado_por_tecnico_secundario_en;
            DB::table('pedidos_instalacion')->where('pedido_id', $id)->update([
                'fase_flujo' => $confirmedPrimary && $confirmedSecondary ? 'CONFIRMADO' : 'ESPERANDO_CONFIRMACION_TECNICO',
                $column => now('America/Lima'),
                'actualizado_en' => now('America/Lima'),
            ]);
        }, 3);
    }

    public function reserveEquipment(User $actor, int $id, int $equipmentId): void
    {
        DB::transaction(function () use ($actor, $id, $equipmentId): void {
            $order = $this->editable($id);
            $this->requireActiveContract($order);
            $equipment = DB::table('equipos')->where('equipo_id', $equipmentId)->lockForUpdate()->first();
            if (! $equipment || $equipment->estado !== 'DISPONIBLE' || in_array($equipment->condicion, ['MALO', 'MALOGRADO'], true)) {
                $this->fail('equipo_id', 'Selecciona un equipo disponible y en buen estado.');
            }
            if (DB::table('pedido_equipos')->where('equipo_id', $equipmentId)
                ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])->whereNull('fecha_devolucion')->exists()) {
                $this->fail('equipo_id', 'El equipo ya está asignado a otro pedido.');
            }
            DB::table('pedido_equipos')->insert([
                'pedido_id' => $id, 'equipo_id' => $equipmentId,
                'asignado_por' => $actor->getKey(), 'estado_asignacion' => 'ASIGNADO',
            ]);
            DB::table('equipos')->where('equipo_id', $equipmentId)->update(['estado' => 'ASIGNADO']);
            DB::table('movimientos_inventario')->insert([
                'tipo_movimiento' => 'ASIGNACION', 'equipo_id' => $equipmentId,
                'pedido_id' => $id, 'cantidad' => 1, 'responsable_id' => $actor->getKey(),
                'observacion' => 'Equipo reservado para instalación',
            ]);
        }, 3);
    }

    public function reserveMaterial(User $actor, int $id, int $materialId, string $amount): void
    {
        DB::transaction(function () use ($actor, $id, $materialId, $amount): void {
            $order = $this->editable($id);
            $this->requireActiveContract($order);
            $material = DB::table('materiales')->where('material_id', $materialId)->lockForUpdate()->first();
            if (! $material || $material->estado !== 'ACTIVO') $this->fail('material_id', 'Selecciona un material activo.');
            $existing = DB::table('pedido_materiales')->where('pedido_id', $id)->where('material_id', $materialId)->first();
            $reservedElsewhere = DB::table('pedido_materiales as pm')
                ->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'pm.pedido_id')
                ->where('pm.material_id', $materialId)->where('pm.pedido_id', '<>', $id)
                ->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
                ->selectRaw('COALESCE(SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta), 0) as total')
                ->value('total');
            $requested = (int) round((float) $amount * 1000);
            $available = (int) round(((float) $material->stock_actual - (float) $reservedElsewhere) * 1000);
            if ($requested <= 0 || $requested > $available || $requested < (int) round((float) ($existing?->cantidad_utilizada ?? 0) * 1000)) {
                $this->fail('cantidad_asignada', 'La cantidad debe cubrir el consumo registrado y no superar el stock libre.');
            }
            $values = ['cantidad_asignada' => number_format($requested / 1000, 3, '.', '')];
            if ($existing) {
                DB::table('pedido_materiales')->where('pedido_material_id', $existing->pedido_material_id)->update($values);
            } else {
                DB::table('pedido_materiales')->insert($values + [
                    'pedido_id' => $id, 'material_id' => $materialId, 'asignado_por' => $actor->getKey(),
                ]);
            }
        }, 3);
    }

    public function editable(int $id): object
    {
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->lockForUpdate()->first();
        abort_unless($order, 404);
        if (! in_array($order->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true)) {
            $this->fail('pedido', 'Solo se pueden cambiar pedidos no iniciados.');
        }
        return $order;
    }

    public function cancelForContract(int $contractId, int $actorId, string $reason): void
    {
        $orders = DB::table('pedidos_instalacion')->where('contrato_id', $contractId)->lockForUpdate()->get();
        foreach ($orders as $order) {
            if ($order->estado === 'EN_PROCESO') {
                $this->fail('estado', 'La instalación está en proceso. Coordina primero su cierre con el jefe de Instalaciones.');
            }
            if (! in_array($order->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true)) continue;
            $this->cancelPendingOrder($order, $actorId, $reason, [
                'origen' => 'CANCELACION_CONTRATO', 'contrato_id' => $contractId,
            ]);
        }
    }

    public function cancelOrder(int $id, int $actorId, string $reason, ?string $reasonCode = null): void
    {
        DB::transaction(function () use ($id, $actorId, $reason, $reasonCode): void {
            $this->cancelPendingOrder($this->editable($id), $actorId, $reason, array_filter(['motivo_codigo' => $reasonCode]));
        }, 3);
    }

    public function reopenOrder(User $actor, int $id, string $reason): void
    {
        abort_unless($actor->hasPermission('orders.schedule'), 403);
        DB::transaction(function () use ($actor, $id, $reason): void {
            $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->estado !== 'CANCELADO') $this->fail('pedido', 'Solo puedes reabrir un pedido cancelado.');
            $contractState = $order->contrato_id ? DB::table('contratos')->where('contrato_id', $order->contrato_id)->value('estado') : null;
            if ($order->contrato_id && ! in_array($contractState, ['PENDIENTE', 'ACTIVO'], true)) {
                $this->fail('pedido', 'El contrato está cancelado o finalizado. No se puede reabrir este pedido. Registra un contrato nuevo si el cliente desea continuar.');
            }

            $this->restoreCancelledOrder($order, (int) $actor->getKey(), $reason);
        }, 3);
    }

    public function resumeForReactivatedContract(int $contractId, int $actorId): bool
    {
        return DB::transaction(function () use ($contractId, $actorId): bool {
            $contract = DB::table('contratos')->where('contrato_id', $contractId)->lockForUpdate()->first();
            if (! $contract || $contract->estado !== 'ACTIVO') return false;

            $order = DB::table('pedidos_instalacion')->where('contrato_id', $contractId)->lockForUpdate()->first();
            if (! $order) return false;
            if ($order->estado !== 'CANCELADO') return false;

            $this->restoreCancelledOrder($order, $actorId, 'Contrato reactivado; pedido retomado automáticamente para coordinar la instalación.');
            return true;
        }, 3);
    }

    public function cancelPendingForExpiredContract(int $contractId): void
    {
        $orders = DB::table('pedidos_instalacion')->where('contrato_id', $contractId)->lockForUpdate()->get();
        foreach ($orders as $order) {
            if (! in_array($order->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true)) continue;
            $actorId = (int) ($order->asignado_por ?: $order->coordinado_por ?: $order->creado_por);
            $this->cancelPendingOrder($order, $actorId, 'Contrato vencido; pedido pendiente cerrado automáticamente.', [
                'origen' => 'VENCIMIENTO_CONTRATO', 'contrato_id' => $contractId,
            ]);
        }
    }

    private function restoreCancelledOrder(object $order, int $actorId, string $reason): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $order->pedido_id)->update([
            'estado' => 'PENDIENTE', 'fase_flujo' => 'PENDIENTE_COORDINACION',
            'fecha_programada' => null, 'fecha_original' => null, 'franja_horaria' => null,
            'canal_coordinacion' => null, 'detalle_coordinacion' => null,
            'coordinado_por' => null, 'coordinado_en' => null,
            'tecnico_id' => null, 'asignado_por' => null, 'asignado_en' => null,
            'confirmado_por_tecnico_en' => null, 'notificado_en' => null,
            'motivo_reprogramacion' => null, 'actualizado_en' => now('America/Lima'),
        ]);
        DB::table('pedido_historial_eventos')->insert([
            'pedido_id' => $order->pedido_id, 'usuario_id' => $actorId, 'tipo' => 'REAPERTURA',
            'motivo' => $reason, 'contexto' => json_encode(['fecha_previa' => $order->fecha_programada, 'tecnico_previo' => $order->tecnico_id]),
            'registrado_en' => now('America/Lima'),
        ]);
    }

    private function cancelPendingOrder(object $order, int $actorId, string $reason, array $context = []): void
    {
        DB::table('pedido_solicitudes_recurso')->where('pedido_id', $order->pedido_id)
            ->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])->update([
                'estado' => 'ANULADA', 'cerrado_por' => $actorId, 'cerrado_en' => now('America/Lima'),
                'decision_observacion' => 'El pedido fue cancelado.',
            ]);
        DB::table('pedido_reprogramaciones')->where('pedido_id', $order->pedido_id)->where('estado', 'SOLICITADA')->update([
            'estado' => 'ANULADA', 'resuelto_por' => $actorId, 'resuelto_en' => now('America/Lima'),
            'decision_observacion' => 'El pedido fue cancelado.',
        ]);
        $equipmentIds = DB::table('pedido_equipos')->where('pedido_id', $order->pedido_id)
            ->where('estado_asignacion', 'ASIGNADO')->pluck('equipo_id');
        DB::table('pedido_equipos')->where('pedido_id', $order->pedido_id)->where('estado_asignacion', 'ASIGNADO')
            ->update(['estado_asignacion' => 'CANCELADO', 'fecha_devolucion' => now('America/Lima')]);
        DB::table('equipos')->whereIn('equipo_id', $equipmentIds)->update(['estado' => 'DISPONIBLE']);
        foreach ($equipmentIds as $equipmentId) {
            DB::table('movimientos_inventario')->insert([
                'tipo_movimiento' => 'DEVOLUCION', 'equipo_id' => $equipmentId,
                'pedido_id' => $order->pedido_id, 'cantidad' => 1, 'responsable_id' => $actorId,
                'observacion' => 'Liberado por cancelación del pedido',
            ]);
        }
        foreach (DB::table('pedido_materiales')->where('pedido_id', $order->pedido_id)->get() as $material) {
            DB::table('pedido_materiales')->where('pedido_material_id', $material->pedido_material_id)->update([
                'cantidad_devuelta' => number_format(max(0, (float) $material->cantidad_asignada - (float) $material->cantidad_utilizada), 3, '.', ''),
            ]);
        }
        DB::table('pedidos_instalacion')->where('pedido_id', $order->pedido_id)->update([
            'estado' => 'CANCELADO', 'fase_flujo' => $order->fase_flujo ? 'CANCELADO' : null,
            'actualizado_en' => now('America/Lima'),
        ]);
        DB::table('pedido_historial_eventos')->insert([
            'pedido_id' => $order->pedido_id, 'usuario_id' => $actorId, 'tipo' => 'CANCELACION',
            'motivo' => $reason, 'contexto' => json_encode($context + ['fecha_programada' => $order->fecha_programada, 'tecnico_id' => $order->tecnico_id]),
            'registrado_en' => now('America/Lima'),
        ]);
    }

    private function requireActiveContract(object $order): void
    {
        if ($order->contrato_id && ! in_array(DB::table('contratos')->where('contrato_id', $order->contrato_id)->value('estado'), ['PENDIENTE', 'ACTIVO'], true)) {
            $this->fail('pedido', 'El contrato está cancelado o finalizado. No se puede continuar la instalación.');
        }
    }

    public function availableTechniciansForVisit(int $orderId, ?string $scheduledAt, ?string $window = null)
    {
        if (! $scheduledAt) return collect();
        $busyIds = $this->busyTechnicianIds($orderId, $scheduledAt, $window);

        return Employee::eligible()->where('cargo', 'Técnico de Campo')
            ->when($busyIds !== [], fn ($query) => $query->whereNotIn('empleado_id', $busyIds))
            ->orderBy('nombres')->orderBy('apellidos')->get();
    }

    private function hasTechnicianScheduleConflict(int $technicianId, int $excludedOrderId, string $scheduledAt, ?string $window = null): bool
    {
        return in_array($technicianId, $this->busyTechnicianIds($excludedOrderId, $scheduledAt, $window), true);
    }

    private function busyTechnicianIds(int $excludedOrderId, string $scheduledAt, ?string $window = null): array
    {
        $scheduled = Carbon::parse($scheduledAt, 'America/Lima');
        $window ??= $scheduled->hour < 12 ? '08:00 a 12:00' : '14:00 a 18:00';

        return DB::table('pedidos_instalacion')
            ->where('pedido_id', '<>', $excludedOrderId)
            ->whereIn('estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
            ->whereNotNull('fecha_programada')
            ->whereDate('fecha_programada', $scheduled->toDateString())
            ->where(fn ($query) => $query->whereNotNull('tecnico_id')->orWhereNotNull('tecnico_secundario_id'))
            ->get(['estado', 'fecha_programada', 'franja_horaria', 'tecnico_id', 'tecnico_secundario_id'])
            ->filter(fn ($visit) => $visit->estado === 'EN_PROCESO'
                || ($visit->franja_horaria ?: (Carbon::parse($visit->fecha_programada, 'America/Lima')->hour < 12
                    ? '08:00 a 12:00' : '14:00 a 18:00')) === $window
                || Carbon::parse($visit->fecha_programada, 'America/Lima')->hour === $scheduled->hour)
            ->flatMap(fn ($visit) => [$visit->tecnico_id, $visit->tecnico_secundario_id])
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function notifyTechnician(int $id, int $employeeId, string $event = 'assignment'): void
    {
        $user = User::where('empleado_id', $employeeId)->where('estado', 'ACTIVO')->first();
        if (! $user) {
            Log::warning('Pedido de instalación sin cuenta técnica activa', ['pedido_id' => $id]);
            return;
        }
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->first();
        try {
            $this->sendTechnicianMail($order, $user, $event);
            DB::table('pedidos_instalacion')->where('pedido_id', $id)->update(['notificado_en' => now('America/Lima')]);
        } catch (\Throwable $error) {
            Log::error('No se pudo notificar la asignación técnica', ['pedido_id' => $id, 'error' => $error->getMessage()]);
        }
    }

    public function testTechnicianMail(int $id, bool $send = true): array
    {
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->first();
        if (! $order) {
            $this->fail('pedido', 'No existe ese pedido de instalación.');
        }
        if (! $order->tecnico_id) {
            $this->fail('pedido', 'Este pedido aún no tiene un técnico asignado.');
        }
        $user = User::where('empleado_id', $order->tecnico_id)->where('estado', 'ACTIVO')->first();
        if (! $user?->isTechnician()) {
            $this->fail('tecnico_id', 'El técnico asignado no tiene una cuenta técnica activa.');
        }
        $url = rtrim((string) config('app.url'), '/').route('technician.show', $id, false);
        $loginUrl = rtrim((string) config('app.url'), '/').route('login', [], false);
        if ($send) {
            $this->sendTechnicianMail($order, $user, 'test');
        }

        return ['correo' => $user->correo, 'codigo' => $order->codigo, 'url' => $url, 'login_url' => $loginUrl];
    }

    private function sendTechnicianMail(object $order, User $user, string $event): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $scheduledAt = Carbon::parse($order->fecha_programada, 'America/Lima')->locale('es');
        $content = match ($event) {
            'reprogrammed' => [
                'subject' => 'Visita reprogramada · '.$order->codigo,
                'eventLabel' => 'VISITA REPROGRAMADA',
                'title' => 'Se reprogramó tu visita',
                'intro' => 'La fecha u hora de esta instalación cambió. Revisa la nueva programación y confirma la asignación desde tu panel técnico.',
            ],
            'reprogrammed_assignment' => [
                'subject' => 'Nueva asignación · visita reprogramada · '.$order->codigo,
                'eventLabel' => 'NUEVA ASIGNACIÓN · VISITA REPROGRAMADA',
                'title' => 'Te asignaron una visita reprogramada',
                'intro' => 'Te asignaron esta instalación después de que su visita fuera reprogramada. Revisa el nuevo horario y confirma la asignación.',
            ],
            'test' => [
                'subject' => 'Prueba de acceso técnico · '.$order->codigo,
                'eventLabel' => 'CORREO DE PRUEBA',
                'title' => 'Prueba de acceso al sistema',
                'intro' => 'Este correo comprueba el acceso al sistema y a la ficha de instalación.',
            ],
            default => [
                'subject' => 'Nueva instalación asignada · '.$order->codigo,
                'eventLabel' => 'NUEVA ASIGNACIÓN',
                'title' => 'Te asignaron una instalación',
                'intro' => 'Se te asignó esta instalación. Revisa la fecha y la franja acordada, y confirma la asignación desde tu panel técnico.',
            ],
        };
        $data = [
            'eventLabel' => $content['eventLabel'],
            'title' => $content['title'],
            'code' => $order->codigo,
            'date' => $scheduledAt->isoFormat('dddd D [de] MMMM [de] YYYY'),
            'startTime' => $scheduledAt->format('H:i'),
            'timeWindow' => $order->franja_horaria ?: 'Por coordinar',
            'loginUrl' => $baseUrl.route('login', [], false),
            'orderUrl' => $baseUrl.route('technician.show', $order->pedido_id, false),
            'intro' => $content['intro'],
        ];

        Mail::send(
            ['html' => 'emails.technician-assignment', 'text' => 'emails.technician-assignment-text'],
            $data,
            fn ($message) => $message->to($user->correo)->subject($content['subject']),
        );
    }

    private function notifyClient(int $id, int $clientId): void
    {
        $client = DB::table('clientes')->where('cliente_id', $clientId)->first(['correo']);
        if (! $client?->correo) return;
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->first(['codigo', 'fecha_programada', 'franja_horaria']);
        try {
            Mail::raw("La visita de instalación {$order->codigo} fue reprogramada para {$order->fecha_programada} ({$order->franja_horaria}). Si no acordaste este cambio, comunícate con Lunnys Perú.",
                fn ($message) => $message->to($client->correo)->subject('Nueva fecha de instalación · '.$order->codigo));
        } catch (\Throwable $error) {
            Log::error('No se pudo notificar la reprogramación al cliente', ['pedido_id' => $id, 'error' => $error->getMessage()]);
        }
    }

    public function validEvidence(object $change): bool
    {
        if (! $change->evidencia_ruta || ! $change->evidencia_sha256
            || ! preg_match('#^installation-rescheduling/[a-zA-Z0-9]+\.(pdf|jpg|jpeg|png|webp)$#D', $change->evidencia_ruta)
            || ! Storage::disk('local')->exists($change->evidencia_ruta)) return false;

        return hash_equals($change->evidencia_sha256,
            hash_file('sha256', Storage::disk('local')->path($change->evidencia_ruta)));
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
