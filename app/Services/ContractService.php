<?php

namespace App\Services;

use App\Repositories\ContractPeriodRepository;
use App\Repositories\ContractRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SaleRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        private PaymentRepository $payments,
        private SaleRepository $sales,
        private InstallationOrderService $installationOrders,
        private InstallationDriveService $installationDrive
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

    public function pendingCancellationCount(): int
    {
        if (! $this->cancellationWorkflowReady()) return 0;

        return (int) DB::table('solicitudes_cancelacion_contrato')->where('estado', 'PENDIENTE')->count();
    }

    public function cancellationRequests(array $filters = [])
    {
        if (! $this->cancellationWorkflowReady()) return collect();

        $query = DB::table('solicitudes_cancelacion_contrato as s')
            ->join('contratos as c', 'c.contrato_id', '=', 's.contrato_id')
            ->join('clientes as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->join('usuarios as u', 'u.usuario_id', '=', 's.solicitado_por')
            ->leftJoin('usuarios as r', 'r.usuario_id', '=', 's.resuelto_por')
            ->select('s.*', 'c.codigo', 'cl.nombres_razon_social', 'cl.apellidos',
                'u.nombre_usuario as solicitante_usuario', 'r.nombre_usuario as revisor_usuario');

        if (filled($filters['estado'] ?? null)) $query->where('s.estado', $filters['estado']);
        if (filled($filters['q'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
            $query->where(function ($builder) use ($pattern): void {
                $builder->where('c.codigo', 'like', $pattern)
                    ->orWhere('cl.nombres_razon_social', 'like', $pattern)
                    ->orWhere('cl.apellidos', 'like', $pattern)
                    ->orWhere('u.nombre_usuario', 'like', $pattern)
                    ->orWhere('s.categoria', 'like', $pattern)
                    ->orWhere('s.explicacion', 'like', $pattern);
            });
        }

        return $query->orderByRaw("CASE s.estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END")
            ->orderByDesc('s.solicitado_en')->paginate(12)->withQueryString();
    }

    public function finalizeExpired(): int
    {
        return $this->contracts->finalizeExpired(now()->toDateString(),
            fn (object $contract) => $this->installationOrders->cancelPendingForExpiredContract((int) $contract->contrato_id),
            function (object $contract): void {
                if ($this->cancellationWorkflowReady()) {
                    DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $contract->contrato_id)
                        ->where('estado', 'PENDIENTE')->update([
                            'estado' => 'CERRADA', 'resuelto_en' => now('America/Lima'),
                            'decision_motivo' => 'La solicitud se cerró porque el contrato venció antes de la decisión.',
                        ]);
                }
                $this->refreshClientLifecycle((int) $contract->cliente_id);
            });
    }

    public function formData(?int $id = null, ?\App\Models\User $actor = null): array
    {
        $data = $this->contracts->formOptions();
        $data['plans'] = $this->plans->active();
        $data['contract'] = $id ? $this->contracts->find($id) : null;
        $data['canChooseResponsible'] = $actor?->isManager() ?? true;
        $responsibleId = $data['contract']?->responsable_id ?? $actor?->empleado_id;
        $data['assignedResponsible'] = $responsibleId
            ? $this->contracts->option('employees', (int) $responsibleId)
            : null;
        if (! $data['canChooseResponsible']) {
            $data['employees'] = collect();
        }
        $data['storedPdfAvailable'] = $data['contract']?->pdf_contrato_ruta
            && Storage::disk('local')->exists($data['contract']->pdf_contrato_ruta);
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

    public function create(array $data, int $actorId): int
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $actorId): int {
                    $plan = $this->plans->lock($data['plan_id']);
                    if ($plan->estado !== 'ACTIVO') {
                        throw ValidationException::withMessages(['plan_id' => 'Este plan fue inhabilitado. Selecciona otro plan activo.']);
                    }
                    $client = $this->validateSelections($data);
                    $end = $this->endDate($data);

                    $contractId = $this->contracts->create(Arr::only($data, [
                        'cliente_id', 'plan_id', 'zona_id', 'responsable_id', 'fecha_inicio',
                        'duracion_meses', 'observaciones',
                    ]) + [
                        'codigo' => 'CON-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                        'fecha_fin' => $end,
                        'precio_contratado' => $plan->precio_mensual,
                        'direccion_servicio' => $client->direccion,
                        'estado' => 'PENDIENTE',
                    ]);
                    $contract = $this->contracts->findDetailed($contractId);
                    $linkedOrder = $this->installationOrders->linkFormalContract($contract, $actorId);
                    if (! $linkedOrder) $this->installationOrders->createForContract($contract, $actorId);

                    return $contractId;
                });
            } catch (UniqueConstraintViolationException $error) {
                if (! str_contains($error->getMessage(), 'uq_contrato_codigo') || $attempt === 2) {
                    throw $error;
                }
            }
        }

        throw new \LogicException('No se pudo generar un código de contrato único.');
    }

    public function update(int $id, array $data, ?int $userId = null): array
    {
        $path = $this->storeSignedPdf($data);
        $oldPath = null;
        $feedback = ['message' => 'Contrato actualizado correctamente.', 'kind' => 'updated'];
        try {
            DB::transaction(function () use ($id, $data, $path, $userId, &$oldPath, &$feedback): void {
                $current = $this->contracts->find($id, true);
                if ($current->estado === 'CANCELADO') {
                    throw ValidationException::withMessages(['contrato' => 'Los contratos cancelados son únicamente de consulta.']);
                }
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
                if ($this->cancellationWorkflowReady()
                    && DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $id)->where('estado', 'PENDIENTE')->exists()) {
                    throw ValidationException::withMessages(['contrato' => 'Este contrato tiene una solicitud de cancelación en revisión. Espera la decisión antes de editarlo.']);
                }
                if ($termsChanged && in_array($current->estado, ['ACTIVO', 'PENDIENTE'], true) && ($current->estado === 'ACTIVO' || $current->pdf_contrato_ruta)) {
                    $attributes += ['estado' => 'PENDIENTE', 'firma_revision' => null, 'firma_validada_en' => null];
                    $this->sales->annul($id);
                    $oldPath = $current->pdf_contrato_ruta;
                    $attributes['pdf_contrato_ruta'] = null;
                    $feedback = ['message' => 'Contrato actualizado. Debes subir el documento con los nuevos datos para activarlo.', 'kind' => 'pending'];
                }
                if ($path) {
                    $oldPath = $current->pdf_contrato_ruta;
                    $attributes['pdf_contrato_ruta'] = $path;
                }
                $this->contracts->update($id, $attributes);
                $this->installationOrders->linkFormalContract($this->contracts->findDetailed($id), $userId ?? (auth()->id() ?? 1));
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
                    if ($signed) {
                        $this->sales->confirm($this->contracts->find($id));
                    }

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

        if ($path && DB::table('contratos')->where('contrato_id', $id)->value('firma_revision') === 'VERIFICADA') {
            $orderId = DB::table('pedidos_instalacion')->where('contrato_id', $id)->value('pedido_id');
            if ($orderId) {
                $sync = fn () => $this->installationDrive->syncVerifiedContract((int) $orderId);
                if (DB::transactionLevel() > 0) DB::afterCommit($sync);
                else $sync();
            }
        }

        return $feedback;
    }

    public function requestCancellation(int $contractId, array $data, UploadedFile $evidence, int $userId): int
    {
        $path = 'contract-cancellations/'.Str::uuid().'.'.$evidence->extension();
        $stored = Storage::disk('local')->putFileAs('contract-cancellations', $evidence, basename($path));
        if (! $stored) {
            throw ValidationException::withMessages(['evidencia' => 'No se pudo guardar el archivo de respaldo. Vuelve a intentarlo.']);
        }
        $evidenceHash = hash_file('sha256', $evidence->getRealPath());

        try {
            return DB::transaction(function () use ($contractId, $data, $userId, $path, $evidence, $evidenceHash): int {
                $contract = $this->contracts->find($contractId, true);
                if (! in_array($contract->estado, ['PENDIENTE', 'ACTIVO'], true)) {
                    throw ValidationException::withMessages(['contrato' => 'Solo se puede solicitar la cancelación de contratos pendientes o activos.']);
                }
                if (DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $contractId)->where('estado', 'PENDIENTE')->exists()) {
                    throw ValidationException::withMessages(['contrato' => 'Ya hay una solicitud de cancelación pendiente para este contrato.']);
                }
                if (DB::table('pedidos_instalacion')->where('contrato_id', $contractId)->where('estado', 'EN_PROCESO')->exists()) {
                    throw ValidationException::withMessages(['contrato' => 'No se puede solicitar la cancelación mientras la instalación está en proceso. Coordina primero su cierre con el jefe de Instalaciones.']);
                }
                if ($contract->estado === 'ACTIVO' && ($contract->firma_revision !== 'VERIFICADA' || ! $contract->pdf_contrato_ruta || ! Storage::disk('local')->exists($contract->pdf_contrato_ruta))) {
                    throw ValidationException::withMessages(['contrato' => 'No se puede cancelar: el contrato activo no conserva un documento firmado verificado.']);
                }

                return DB::table('solicitudes_cancelacion_contrato')->insertGetId([
                    'contrato_id' => $contractId, 'solicitado_por' => $userId,
                    'solicitado_en' => now('America/Lima'), 'categoria' => $data['categoria'],
                    'explicacion' => trim($data['explicacion']), 'evidencia_ruta' => $path,
                    'evidencia_nombre' => basename((string) $evidence->getClientOriginalName()),
                    'evidencia_sha256' => $evidenceHash, 'contrato_sha256' => $this->contractSnapshot($contract),
                    'estado' => 'PENDIENTE',
                ]);
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function approveCancellation(int $contractId, int $requestId, int $approverId): void
    {
        DB::transaction(function () use ($contractId, $requestId, $approverId): void {
            $request = DB::table('solicitudes_cancelacion_contrato')->where('solicitud_cancelacion_id', $requestId)
                ->where('contrato_id', $contractId)->lockForUpdate()->first();
            if (! $request || $request->estado !== 'PENDIENTE') {
                throw ValidationException::withMessages(['solicitud' => 'Esta solicitud ya fue resuelta o no está disponible.']);
            }
            if ((int) $request->solicitado_por === $approverId) {
                throw ValidationException::withMessages(['solicitud' => 'Por control de separación de funciones, otra cuenta debe aprobar esta solicitud.']);
            }

            $contract = $this->contracts->find($contractId, true);
            if (! in_array($contract->estado, ['PENDIENTE', 'ACTIVO'], true)) {
                throw ValidationException::withMessages(['contrato' => 'El contrato ya no está pendiente ni activo. Solicita una nueva revisión si corresponde.']);
            }
            if (DB::table('pedidos_instalacion')->where('contrato_id', $contractId)->where('estado', 'EN_PROCESO')->exists()) {
                throw ValidationException::withMessages(['solicitud' => 'La instalación comenzó mientras se revisaba la solicitud. No se puede aprobar la cancelación hasta resolver esa instalación.']);
            }
            if (! hash_equals($request->contrato_sha256, $this->contractSnapshot($contract))) {
                throw ValidationException::withMessages(['solicitud' => 'Los datos del contrato cambiaron desde la solicitud. Rechaza esta solicitud y registra una nueva con evidencia actualizada.']);
            }
            if (! Storage::disk('local')->exists($request->evidencia_ruta)
                || ! hash_equals($request->evidencia_sha256, hash_file('sha256', Storage::disk('local')->path($request->evidencia_ruta)))) {
                throw ValidationException::withMessages(['solicitud' => 'La evidencia de respaldo ya no coincide con el archivo registrado. No se puede aprobar.']);
            }

            $reason = $request->categoria.' — '.$request->explicacion;
            $period = $this->periods->findActivePeriod($contract);
            if (! $period) {
                $period = $this->periods->findLatestPeriod($contract)
                    ? $this->periods->createReactivationPeriod($contract, ['fecha_inicio' => $contract->fecha_inicio, 'duracion_meses' => $contract->duracion_meses])
                    : $this->periods->createInitialPeriod($contract);
            }
            $this->periods->cancel($period, $reason, 'MANUAL', $approverId);
            $this->sales->annul($contractId);
            $this->installationOrders->cancelForContract($contractId, $approverId, $reason);
            $this->contracts->update($contractId, ['estado' => 'CANCELADO', 'periodo_actual' => $period->getKey()]);
            $this->refreshClientLifecycle((int) $contract->cliente_id);
            DB::table('solicitudes_cancelacion_contrato')->where('solicitud_cancelacion_id', $requestId)->update([
                'estado' => 'APROBADA', 'resuelto_por' => $approverId,
                'resuelto_en' => now('America/Lima'), 'decision_motivo' => 'Cancelación aprobada por Gerencia o Administración.',
            ]);
        }, 3);
    }

    public function rejectCancellation(int $contractId, int $requestId, int $reviewerId, string $reason): void
    {
        DB::transaction(function () use ($contractId, $requestId, $reviewerId, $reason): void {
            $request = DB::table('solicitudes_cancelacion_contrato')->where('solicitud_cancelacion_id', $requestId)
                ->where('contrato_id', $contractId)->lockForUpdate()->first();
            if (! $request || $request->estado !== 'PENDIENTE') {
                throw ValidationException::withMessages(['solicitud' => 'Esta solicitud ya fue resuelta o no está disponible.']);
            }
            if ((int) $request->solicitado_por === $reviewerId) {
                throw ValidationException::withMessages(['solicitud' => 'Otra cuenta debe revisar una solicitud que tú iniciaste.']);
            }
            DB::table('solicitudes_cancelacion_contrato')->where('solicitud_cancelacion_id', $requestId)->update([
                'estado' => 'RECHAZADA', 'resuelto_por' => $reviewerId,
                'resuelto_en' => now('America/Lima'), 'decision_motivo' => trim($reason),
            ]);
        }, 3);
    }

    public function cancellationHistory(int $contractId)
    {
        if (! $this->cancellationWorkflowReady()) return collect();

        return DB::table('solicitudes_cancelacion_contrato as s')
            ->join('usuarios as solicitante', 'solicitante.usuario_id', '=', 's.solicitado_por')
            ->leftJoin('usuarios as revisor', 'revisor.usuario_id', '=', 's.resuelto_por')
            ->where('s.contrato_id', $contractId)->orderByDesc('s.solicitud_cancelacion_id')
            ->get(['s.*', 'solicitante.nombre_usuario as solicitante_usuario', 'revisor.nombre_usuario as revisor_usuario']);
    }

    public function cancellationWorkflowReady(): bool
    {
        return Schema::hasTable('solicitudes_cancelacion_contrato');
    }

    private function contractSnapshot(object $contract): string
    {
        $values = [];
        foreach (['cliente_id', 'plan_id', 'zona_id', 'responsable_id', 'fecha_inicio', 'fecha_fin', 'duracion_meses', 'precio_contratado', 'direccion_servicio', 'observaciones', 'estado', 'firma_revision', 'pdf_contrato_ruta'] as $key) {
            $values[$key] = (string) ($contract->$key ?? '');
        }
        $values['pdf_sha256'] = $contract->pdf_contrato_ruta && Storage::disk('local')->exists($contract->pdf_contrato_ruta)
            ? hash_file('sha256', Storage::disk('local')->path($contract->pdf_contrato_ruta)) : '';

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function validateSelections(array $data, ?object $current = null): object
    {
        $client = $this->contracts->activeClient((int) $data['cliente_id']);
        $zone = $this->contracts->activeZone((int) $data['zona_id']);
        $employee = $this->contracts->activeEmployee((int) $data['responsable_id']);
        foreach ([['zona_id', $zone, 'ACTIVA']] as [$field, $record, $active]) {
            if (! $record || ($record->estado !== $active && (! $current || (int) $current->$field !== (int) $data[$field]))) {
                throw ValidationException::withMessages([$field => 'Selecciona un registro activo.']);
            }
        }
        if (! $client || (! in_array($client->estado, ['ACTIVO', 'PENDIENTE'], true)
            && (! $current || (int) $current->cliente_id !== (int) $data['cliente_id']))) {
            throw ValidationException::withMessages(['cliente_id' => 'Selecciona un cliente activo o pendiente de instalación.']);
        }
        if (! $employee) {
            throw ValidationException::withMessages(['responsable_id' => 'La cuenta que registra debe estar vinculada a un Asistente de Ventas activo. Pide a Gerencia que revise el vínculo de empleado.']);
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
     * @param  int  $userId  ID del usuario que activa (para registrar en pagos)
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
     * @param  array  $data  Debe contener: fecha_inicio, duracion_meses
     * @param  int  $userId  ID del usuario que reactiva
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

            if (! $client || ! in_array($client->estado, ['ACTIVO', 'PENDIENTE', 'INACTIVO'], true)) {
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
            DB::table('clientes')->where('cliente_id', $contract->cliente_id)->update([
                'estado' => 'ACTIVO', 'actualizado_en' => now('America/Lima'),
            ]);

            $orderResumed = $this->installationOrders->resumeForReactivatedContract($contractId, $userId);

            // Generar nuevos pagos mensuales para el nuevo período
            $paymentsGenerated = $this->payments->generateMonthlyPayments(
                $this->contracts->find($contractId),
                $userId
            );
            $this->sales->confirm($this->contracts->find($contractId));

            return [
                'message' => sprintf('Contrato reactivado correctamente. Se creó el período %d y se generaron %d pagos mensuales.%s',
                    $nuevoPeriodo->numero_periodo, count($paymentsGenerated),
                    $orderResumed ? ' El pedido de instalación volvió a coordinación para confirmar la visita.' : ''),
                'kind' => 'reactivated',
                'periodo_numero' => $nuevoPeriodo->numero_periodo,
                'pagos_generados' => count($paymentsGenerated),
            ];
        });
    }

    /**
     * Obtener datos para el formulario de reactivación
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
     * @return LengthAwarePaginator
     */
    public function getCancelledContracts()
    {
        return $this->contracts->getCancelledContracts();
    }

    /** Keep the client state aligned with its remaining service and installation work. */
    private function refreshClientLifecycle(int $clientId): void
    {
        $hasActiveContract = DB::table('contratos')->where('cliente_id', $clientId)->where('estado', 'ACTIVO')->exists();
        $hasPendingContract = DB::table('contratos')->where('cliente_id', $clientId)->where('estado', 'PENDIENTE')->exists();
        $hasOpenInstallation = DB::table('pedidos_instalacion')->where('cliente_id', $clientId)
            ->whereIn('estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])->exists();

        $state = $hasActiveContract ? 'ACTIVO' : (($hasPendingContract || $hasOpenInstallation) ? 'PENDIENTE' : null);
        if ($state === null) {
            DB::table('clientes')->where('cliente_id', $clientId)->whereIn('estado', ['ACTIVO', 'PENDIENTE'])
                ->update(['estado' => 'INACTIVO', 'actualizado_en' => now('America/Lima')]);

            return;
        }

        DB::table('clientes')->where('cliente_id', $clientId)
            ->update(['estado' => $state, 'actualizado_en' => now('America/Lima')]);
    }
}
