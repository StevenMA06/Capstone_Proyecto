<?php

namespace App\Services;

use App\Repositories\ContractPeriodRepository;
use App\Repositories\ContractRepository;
use App\Repositories\PaymentRepository;
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
    public function __construct(
        private ContractRepository $contracts,
        private PlanRepository $plans,
        private ContractSignatureVerifier $signatures,
        private ContractPeriodRepository $periods,
        private PaymentRepository $payments
    ) {}

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

    public function finalizeExpired(): int
    {
        return $this->contracts->finalizeExpired(now()->toDateString());
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
        if ($data['contract']) {
            foreach ([
                ['plans', 'plan_id', $data['contract']->plan_id],
                ['clients', 'cliente_id', $data['contract']->cliente_id],
                ['zones', 'zona_id', $data['contract']->zona_id],
                ['employees', 'empleado_id', $data['contract']->responsable_id],
            ] as [$collection, $key, $value]) {
                if (! $data[$collection]->contains($key, $value)) {
                    $option = $this->contracts->option($collection, $value);
                    if ($option) {
                        $data[$collection]->push($option);
                    }
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
                if (($data['estado'] ?? null) === 'CANCELADO') {
                    if ($current->estado !== 'ACTIVO' || $current->firma_revision !== 'VERIFICADA' || ! $current->pdf_contrato_ruta || ! Storage::disk('local')->exists($current->pdf_contrato_ruta)) {
                        throw ValidationException::withMessages(['estado' => 'Solo puedes cerrar un contrato activo con la firma del cliente verificada.']);
                    }
                    if ($termsChanged || $path) {
                        throw ValidationException::withMessages(['estado' => 'Para cerrar el contrato, conserva los datos y el PDF actual.']);
                    }
                    $attributes['estado'] = 'CANCELADO';
                    $feedback = ['message' => 'El contrato se canceló correctamente.', 'kind' => 'cancelled'];
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
                    $expiredAtConfirmation = $signed && $attributes['fecha_fin'] < now()->toDateString();
                    $newState = $expiredAtConfirmation ? 'FINALIZADO' : ($signed ? 'ACTIVO' : 'PENDIENTE');
                    
                    $this->contracts->update($id, [
                        'estado' => $newState,
                        'firma_revision' => $signed ? 'VERIFICADA' : 'SIN_FIRMA',
                        'firma_validada_en' => $signed ? now() : null,
                    ]);

                    // Si el contrato pasa a ACTIVO, crear período inicial y generar pagos
                    if ($newState === 'ACTIVO' && $current->estado !== 'ACTIVO') {
                        $this->activateContractWithPayments($id, auth()->id() ?? 1);
                    }
                    
                    $feedback = $expiredAtConfirmation
                        ? ['message' => 'Se detectó la firma del cliente. El contrato quedó finalizado porque su vigencia ya terminó.', 'kind' => 'finalized']
                        : ($signed
                        ? ['message' => 'Se detectó la firma del cliente y el contrato se activó automáticamente. Se generaron los pagos mensuales correspondientes.', 'kind' => 'activated']
                        : ['message' => 'Se guardó el PDF. No se detectó la firma del cliente; el contrato sigue pendiente.', 'kind' => 'pending']);
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
        ] as [$field, $record, $active]) {
            if (! $record || ($record->estado !== $active && (! $current || (int) $current->$field !== (int) $data[$field]))) {
                throw ValidationException::withMessages([$field => 'Selecciona un registro activo.']);
            }
        }
        if (! $employee) {
            throw ValidationException::withMessages(['responsable_id' => 'Selecciona un Asistente de Ventas activo como responsable.']);
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

    /**
     * Activar un contrato: crear período inicial y generar pagos automáticamente
     * 
     * @param int $contractId
     * @param int $userId ID del usuario que activa (para registrar en pagos)
     * @return void
     */
    private function activateContractWithPayments(int $contractId, int $userId): void
    {
        $contract = $this->contracts->find($contractId);

        // Verificar si ya existe un período activo
        $existingPeriod = $this->periods->findActivePeriod($contract);

        if (! $existingPeriod) {
            // Crear período inicial
            $period = $this->periods->createInitialPeriod($contract);

            // Vincular el período al contrato
            $this->contracts->update($contractId, [
                'periodo_actual' => $period->getKey(),
            ]);
        }

        // Generar pagos mensuales automáticamente
        $this->payments->generateMonthlyPayments($contract, $userId);
    }

    /**
     * Generar pagos para un contrato específico
     * Útil para regenerar pagos o generar pagos de contratos antiguos
     * 
     * @param int $contractId
     * @param int $userId
     * @return int Cantidad de pagos generados
     */
    public function generatePaymentsForContract(int $contractId, int $userId): int
    {
        $contract = $this->contracts->find($contractId);

        if ($contract->estado !== 'ACTIVO') {
            throw ValidationException::withMessages([
                'contrato' => 'Solo se pueden generar pagos para contratos activos.',
            ]);
        }

        $payments = $this->payments->generateMonthlyPayments($contract, $userId);

        return count($payments);
    }

    /**
     * Reactivar un contrato cancelado
     * Conserva el historial anterior y crea un nuevo período contractual
     * 
     * @param int $contractId
     * @param array $data Debe contener: fecha_inicio, duracion_meses
     * @param int $userId ID del usuario que reactiva
     * @return array Feedback con mensaje y tipo
     */
    public function reactivate(int $contractId, array $data, int $userId): array
    {
        return DB::transaction(function () use ($contractId, $data, $userId): array {
            $contract = $this->contracts->find($contractId, true);

            // Validar que el contrato esté CANCELADO
            if ($contract->estado !== 'CANCELADO') {
                throw ValidationException::withMessages([
                    'contrato' => 'Solo se pueden reactivar contratos cancelados. Estado actual: '.ucfirst(strtolower($contract->estado)).'.',
                ]);
            }

            // Validar el plan sigue activo
            $plan = $this->plans->lock($contract->plan_id);
            if ($plan->estado !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'plan_id' => 'El plan del contrato fue inhabilitado. No se puede reactivar.',
                ]);
            }

            // Validar que el cliente, zona y responsable sigan activos
            $client = $this->contracts->activeClient($contract->cliente_id);
            $zone = $this->contracts->activeZone($contract->zona_id);
            $employee = $this->contracts->activeEmployee($contract->responsable_id);

            if (! $client || $client->estado !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'cliente' => 'El cliente del contrato no está activo. No se puede reactivar.',
                ]);
            }

            if (! $zone || $zone->estado !== 'ACTIVA') {
                throw ValidationException::withMessages([
                    'zona' => 'La zona del contrato no está activa. No se puede reactivar.',
                ]);
            }

            if (! $employee) {
                throw ValidationException::withMessages([
                    'responsable' => 'El responsable del contrato no está activo. No se puede reactivar.',
                ]);
            }

            // Validar nueva fecha de inicio
            $nuevaFechaInicio = $data['fecha_inicio'];
            if ($nuevaFechaInicio < now()->toDateString()) {
                throw ValidationException::withMessages([
                    'fecha_inicio' => 'La fecha de inicio debe ser hoy o una fecha futura.',
                ]);
            }

            // Validar nueva duración
            $nuevaDuracion = (int) $data['duracion_meses'];
            if ($nuevaDuracion < 1) {
                throw ValidationException::withMessages([
                    'duracion_meses' => 'La duración debe ser de al menos 1 mes.',
                ]);
            }

            // Calcular nueva fecha de fin
            $nuevaFechaFin = CarbonImmutable::parse($nuevaFechaInicio)
                ->addMonthsNoOverflow($nuevaDuracion)
                ->subDay()
                ->format('Y-m-d');

            // Cancelar el período anterior si existe y está activo
            $periodoAnterior = $this->periods->findActivePeriod($contract);
            if ($periodoAnterior) {
                $this->periods->cancel(
                    $periodoAnterior,
                    'Contrato reactivado con nuevo período',
                    'MANUAL',
                    $userId
                );
            }

            // Crear nuevo período contractual
            $nuevoPeriodo = $this->periods->createReactivationPeriod($contract, [
                'fecha_inicio' => $nuevaFechaInicio,
                'duracion_meses' => $nuevaDuracion,
            ]);

            // Actualizar el contrato con los nuevos datos
            $this->contracts->update($contractId, [
                'fecha_inicio' => $nuevaFechaInicio,
                'duracion_meses' => $nuevaDuracion,
                'fecha_fin' => $nuevaFechaFin,
                'estado' => 'ACTIVO',
                'periodo_actual' => $nuevoPeriodo->getKey(),
            ]);

            // Generar nuevos pagos mensuales para el nuevo período
            $paymentsGenerated = $this->payments->generateMonthlyPayments(
                $this->contracts->find($contractId),
                $userId
            );

            return [
                'message' => sprintf(
                    'Contrato reactivado correctamente. Se creó el período %d y se generaron %d pagos mensuales.',
                    $nuevoPeriodo->numero_periodo,
                    count($paymentsGenerated)
                ),
                'kind' => 'reactivated',
                'periodo_numero' => $nuevoPeriodo->numero_periodo,
                'pagos_generados' => count($paymentsGenerated),
            ];
        });
    }

    /**
     * Obtener datos para el formulario de reactivación
     * 
     * @param int $contractId
     * @return array
     */
    public function reactivationFormData(int $contractId): array
    {
        $contract = $this->contracts->find($contractId);

        if ($contract->estado !== 'CANCELADO') {
            throw ValidationException::withMessages([
                'contrato' => 'Solo se pueden reactivar contratos cancelados.',
            ]);
        }

        // Obtener el historial de períodos
        $periodos = $this->periods->findByContract($contract);

        // Obtener información detallada
        $contractDetailed = $this->contracts->findDetailed($contractId);

        return [
            'contract' => $contract,
            'contract_detailed' => $contractDetailed,
            'periods' => $periodos,
            'total_periods' => $periodos->count(),
            'suggested_start_date' => now()->addDays(1)->format('Y-m-d'),
            'previous_duration' => $contract->duracion_meses,
        ];
    }

    /**
     * Obtener contratos cancelados disponibles para reactivación
     * 
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getCancelledContracts()
    {
        return $this->contracts->getCancelledContracts();
    }
}
