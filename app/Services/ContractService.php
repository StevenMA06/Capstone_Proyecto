<?php

namespace App\Services;

use App\Repositories\ContractRepository;
use App\Repositories\PlanRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractService
{
    public function __construct(private ContractRepository $contracts, private PlanRepository $plans, private ContractSignatureVerifier $signatures) {}

    public function search(array $filters)
    {
        return $this->contracts->search($filters);
    }

    public function allPlans()
    {
        return $this->contracts->allPlans();
    }

    public function detail(int $id): object
    {
        return $this->contracts->findDetailed($id);
    }

    public function formData(?int $id = null): array
    {
        $data = $this->contracts->formOptions();
        $data['plans'] = $this->plans->active();
        $data['contract'] = $id ? $this->contracts->find($id) : null;
        $data['storedPdfAvailable'] = $data['contract']?->pdf_contrato_ruta
            && Storage::disk('local')->exists($data['contract']->pdf_contrato_ruta);
        $data['canCancel'] = $data['storedPdfAvailable']
            && $data['contract']->estado === 'ACTIVO'
            && $data['contract']->firma_revision === 'VERIFICADA';
        $data['canFinalize'] = $data['canCancel']
            && CarbonImmutable::parse($data['contract']->fecha_fin)->endOfDay()->isPast();
        if ($data['contract']) {
            foreach ([
                ['plans', 'plan_id', $data['contract']->plan_id],
                ['clients', 'cliente_id', $data['contract']->cliente_id],
                ['zones', 'zona_id', $data['contract']->zona_id],
                ['employees', 'empleado_id', $data['contract']->responsable_id],
            ] as [$collection, $key, $value]) {
                if (! $data[$collection]->contains($key, $value)) {
                    $data[$collection]->push($this->contracts->option($collection, $value));
                }
            }
        }

        return $data;
    }

    public function create(array $data): int
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data): int {
                    $plan = $this->plans->lock($data['plan_id']);
                    if ($plan->estado !== 'ACTIVO') {
                        throw ValidationException::withMessages(['plan_id' => 'Este plan fue inhabilitado. Selecciona otro plan activo.']);
                    }
                    $client = $this->validateSelections($data);
                    $end = $this->endDate($data);

                    return $this->contracts->create(Arr::only($data, [
                        'cliente_id', 'plan_id', 'zona_id', 'responsable_id', 'fecha_inicio',
                        'duracion_meses', 'observaciones',
                    ]) + [
                        'codigo' => 'CON-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                        'fecha_fin' => $end,
                        'precio_contratado' => $plan->precio_mensual,
                        'direccion_servicio' => $client->direccion,
                        'estado' => 'PENDIENTE',
                    ]);
                });
            } catch (UniqueConstraintViolationException $error) {
                if (! str_contains($error->getMessage(), 'uq_contrato_codigo') || $attempt === 2) {
                    throw $error;
                }
            }
        }

        throw new \LogicException('No se pudo generar un código de contrato único.');
    }

    public function update(int $id, array $data): array
    {
        $path = $this->storeSignedPdf($data);
        $oldPath = null;
        $feedback = ['message' => 'Contrato actualizado correctamente.', 'kind' => 'updated'];
        try {
            DB::transaction(function () use ($id, $data, $path, &$oldPath, &$feedback): void {
                $current = $this->contracts->find($id, true);
                $plan = $this->plans->lock($data['plan_id']);
                if ($plan->estado !== 'ACTIVO' && (int) $current->plan_id !== (int) $data['plan_id']) {
                    throw ValidationException::withMessages(['plan_id' => 'Selecciona un plan activo.']);
                }
                $client = $this->validateSelections($data, $current);
                $attributes = Arr::only($data, [
                    'cliente_id', 'plan_id', 'zona_id', 'responsable_id', 'fecha_inicio',
                    'duracion_meses', 'observaciones',
                ]) + [
                    'fecha_fin' => $this->endDate($data),
                    'precio_contratado' => (int) $current->plan_id === (int) $data['plan_id'] ? $current->precio_contratado : $plan->precio_mensual,
                    'direccion_servicio' => $client->direccion,
                ];
                $termsChanged = false;
                foreach (['cliente_id', 'plan_id', 'zona_id', 'responsable_id', 'fecha_inicio', 'duracion_meses', 'fecha_fin', 'precio_contratado', 'direccion_servicio', 'observaciones'] as $field) {
                    if ((string) ($current->$field ?? '') !== (string) ($attributes[$field] ?? '')) {
                        $termsChanged = true;
                        break;
                    }
                }
                if (($data['estado'] ?? null) === 'ACTIVO' && ($current->estado !== 'ACTIVO' || $current->firma_revision !== 'VERIFICADA' || ! $current->pdf_contrato_ruta || ! Storage::disk('local')->exists($current->pdf_contrato_ruta))) {
                    throw ValidationException::withMessages(['estado' => 'La activación solo se realiza automáticamente al verificar la firma del cliente.']);
                }
                if (in_array($data['estado'] ?? null, ['CANCELADO', 'FINALIZADO'], true)) {
                    if ($current->estado !== 'ACTIVO' || $current->firma_revision !== 'VERIFICADA' || ! $current->pdf_contrato_ruta || ! Storage::disk('local')->exists($current->pdf_contrato_ruta)) {
                        throw ValidationException::withMessages(['estado' => 'Solo puedes cerrar un contrato activo con la firma del cliente verificada.']);
                    }
                    if ($termsChanged || $path) {
                        throw ValidationException::withMessages(['estado' => 'Para cerrar el contrato, conserva los datos y el PDF actual.']);
                    }
                    if ($data['estado'] === 'FINALIZADO' && ! CarbonImmutable::parse($current->fecha_fin)->endOfDay()->isPast()) {
                        throw ValidationException::withMessages(['estado' => 'El contrato solo puede finalizar cuando termine su vigencia. Para una baja anticipada usa Cancelado.']);
                    }
                    $attributes['estado'] = $data['estado'];
                    $feedback = $data['estado'] === 'CANCELADO'
                        ? ['message' => 'El contrato se canceló correctamente.', 'kind' => 'cancelled']
                        : ['message' => 'El contrato se finalizó correctamente.', 'kind' => 'finalized'];
                }
                if ($termsChanged && in_array($current->estado, ['ACTIVO', 'PENDIENTE'], true) && ($current->estado === 'ACTIVO' || $current->pdf_contrato_ruta)) {
                    $attributes += ['estado' => 'PENDIENTE', 'firma_revision' => null, 'firma_validada_en' => null];
                    $oldPath = $current->pdf_contrato_ruta;
                    $attributes['pdf_contrato_ruta'] = null;
                    $feedback = ['message' => 'Contrato actualizado. Debes subir el documento con los nuevos datos para activarlo.', 'kind' => 'pending'];
                }
                if ($path) {
                    $oldPath = $current->pdf_contrato_ruta;
                    $attributes['pdf_contrato_ruta'] = $path;
                }
                $this->contracts->update($id, $attributes);
                if ($path) {
                    if (in_array($current->estado, ['CANCELADO', 'FINALIZADO'], true)) {
                        throw ValidationException::withMessages(['documento_pdf' => 'No se puede activar un contrato cancelado o finalizado.']);
                    }
                    $result = $this->signatures->verify($this->contracts->findDetailed($id), $path);
                    if ($result['status'] === 'invalid') {
                        throw ValidationException::withMessages(['documento_pdf' => $result['detail']]);
                    }
                    $signed = $result['status'] === 'signed';
                    $this->contracts->update($id, [
                        'estado' => $signed ? 'ACTIVO' : 'PENDIENTE',
                        'firma_revision' => $signed ? 'VERIFICADA' : 'SIN_FIRMA',
                        'firma_validada_en' => $signed ? now() : null,
                    ]);
                    $feedback = $signed
                        ? ['message' => 'Se detectó la firma del cliente y el contrato se activó automáticamente.', 'kind' => 'activated']
                        : ['message' => 'Se guardó el PDF. No se detectó la firma del cliente; el contrato sigue pendiente.', 'kind' => 'pending'];
                }
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $feedback;
    }

    private function validateSelections(array $data, ?object $current = null): object
    {
        $client = $this->contracts->activeClient((int) $data['cliente_id']);
        $zone = $this->contracts->activeZone((int) $data['zona_id']);
        $employee = $this->contracts->activeEmployee((int) $data['responsable_id']);
        foreach ([
            ['cliente_id', $client, 'ACTIVO'],
            ['zona_id', $zone, 'ACTIVA'],
            ['responsable_id', $employee, 'ACTIVO'],
        ] as [$field, $record, $active]) {
            if (! $record || ($record->estado !== $active && (! $current || (int) $current->$field !== (int) $data[$field]))) {
                throw ValidationException::withMessages([$field => 'Selecciona un registro activo.']);
            }
        }
        if ($client?->distrito && $zone && mb_strtolower(trim($client->distrito)) !== mb_strtolower(trim($zone->distrito))) {
            throw ValidationException::withMessages(['zona_id' => 'La zona seleccionada no cubre el distrito del cliente.']);
        }
        if ($this->contracts->hasPlanCoverage((int) $data['plan_id']) && ! $this->contracts->planCoversZone((int) $data['plan_id'], (int) $data['zona_id'])) {
            throw ValidationException::withMessages(['zona_id' => 'El plan no tiene cobertura activa en la zona seleccionada.']);
        }

        if (! is_string($client->direccion) || trim($client->direccion) === '') {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente seleccionado no tiene una dirección registrada.']);
        }

        return $client;
    }

    private function endDate(array $data): string
    {
        $end = CarbonImmutable::parse($data['fecha_inicio'])->addMonthsNoOverflow((int) $data['duracion_meses'])->subDay();
        if ($end->year > 9999) {
            throw ValidationException::withMessages(['duracion_meses' => 'La duración supera el rango de fechas permitido.']);
        }

        return $end->format('Y-m-d');
    }

    private function storeSignedPdf(array $data): ?string
    {
        if (empty($data['documento_pdf'])) {
            return null;
        }

        $path = $data['documento_pdf']->store('contratos/documentos', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['documento_pdf' => 'No se pudo guardar el PDF del contrato. Inténtalo de nuevo.']);
        }

        return $path;
    }
}
