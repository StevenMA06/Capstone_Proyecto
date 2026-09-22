<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\PaymentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private PaymentRepository $payments) {}

    /**
     * Registrar un nuevo pago (RF-PAG-01)
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $registrant): Payment
    {
        // RF-PAG-02: Validar que el contrato existe y está ACTIVO
        $contract = Contract::find($data['contrato_id']);
        
        if (! $contract) {
            throw ValidationException::withMessages([
                'contrato_id' => 'El contrato especificado no existe.',
            ]);
        }

        if ($contract->estado !== 'ACTIVO') {
            throw ValidationException::withMessages([
                'contrato_id' => 'Solo se pueden registrar pagos para contratos activos. Estado actual: '.ucfirst(strtolower($contract->estado)).'.',
            ]);
        }

        // RN-15: El monto debe ser mayor que cero (también validado en BD)
        if (isset($data['monto']) && $data['monto'] <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del pago debe ser mayor que cero.',
            ]);
        }

        // Generar código único si no se proporciona
        if (empty($data['codigo'])) {
            $data['codigo'] = $this->payments->generateCode();
        }

        // Filtrar atributos y forzar valores de seguridad
        $attributes = $this->attributes($data);
        
        // RN-14: Estado siempre PENDIENTE al crear (ignorar lo que venga del navegador)
        $attributes['estado'] = 'PENDIENTE';
        $attributes['registrado_por'] = $registrant->getKey();
        
        // Remover pago_id si viene del navegador (ignorar)
        unset($attributes['pago_id']);

        return $this->payments->create($attributes);
    }

    /**
     * Búsqueda de pagos con filtros (RF-PAG-05, RF-PAG-06, RF-PAG-08)
     * @param array<string, mixed> $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        return $this->payments->search($filters);
    }

    /**
     * Actualizar un pago existente
     * @param array<string, mixed> $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        // Verificar si el pago puede ser editado
        if (! $payment->canBeEdited()) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede editar un pago con estado: '.ucfirst(strtolower($payment->estado)).'.',
            ]);
        }

        // Si se cambia el contrato, validar que sea ACTIVO
        if (isset($data['contrato_id']) && $data['contrato_id'] != $payment->contrato_id) {
            $contract = Contract::find($data['contrato_id']);
            
            if (! $contract || $contract->estado !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'contrato_id' => 'Solo se pueden asignar pagos a contratos activos.',
                ]);
            }
        }

        // Validar monto si se proporciona
        if (isset($data['monto']) && $data['monto'] <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del pago debe ser mayor que cero.',
            ]);
        }

        return $this->payments->update($payment, $this->attributes($data));
    }

    /**
     * Marcar un pago como realizado
     */
    public function markAsCompleted(Payment $payment, array $data): Payment
    {
        if ($payment->isCompleted()) {
            throw ValidationException::withMessages([
                'estado' => 'El pago ya fue marcado como realizado.',
            ]);
        }

        if ($payment->isCancelled()) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede marcar como realizado un pago anulado.',
            ]);
        }

        $attributes = [
            'estado' => 'REALIZADO',
            'fecha_pago' => $data['fecha_pago'] ?? now(),
        ];

        if (! empty($data['metodo_pago'])) {
            $attributes['metodo_pago'] = $data['metodo_pago'];
        }

        if (! empty($data['referencia_externa'])) {
            $attributes['referencia_externa'] = $data['referencia_externa'];
        }

        if (! empty($data['observaciones'])) {
            $attributes['observaciones'] = $data['observaciones'];
        }

        return $this->payments->update($payment, $attributes);
    }

    /**
     * Anular un pago (RN-49: no elimina físicamente)
     */
    public function cancel(Payment $payment, string $observacion = null): Payment
    {
        if (! $payment->canBeCancelled()) {
            throw ValidationException::withMessages([
                'estado' => 'El pago ya está anulado.',
            ]);
        }

        return $this->payments->cancel($payment, $observacion);
    }

    /**
     * Obtener pagos de un cliente (RF-PAG-05)
     */
    public function findByClient(Client $client): LengthAwarePaginator
    {
        return $this->payments->findByClient($client);
    }

    /**
     * Obtener pagos de un contrato (RF-PAG-06)
     */
    public function findByContract(Contract $contract): LengthAwarePaginator
    {
        return $this->payments->findByContract($contract);
    }

    /**
     * Obtener pagos próximos a vencer (RF-PAG-10)
     */
    public function dueIn(int $days = 7): LengthAwarePaginator
    {
        return $this->payments->dueIn($days);
    }

    /**
     * Obtener pagos vencidos (RF-PAG-11)
     */
    public function overdue(): LengthAwarePaginator
    {
        return $this->payments->overdue();
    }

    /**
     * Generar constancia interna de pago (RF-PAG-09)
     * Devuelve los datos necesarios para la vista de constancia
     * @return array<string, mixed>
     */
    public function generateReceipt(Payment $payment): array
    {
        // Cargar relaciones necesarias
        $payment->load(['contract.client', 'contract.plan', 'registeredBy']);

        $contract = $payment->contract;
        $client = $contract?->client;
        $plan = $contract?->plan;

        if (! $client || ! $contract) {
            throw ValidationException::withMessages([
                'payment' => 'No se puede generar la constancia sin datos del contrato y cliente.',
            ]);
        }

        return [
            'payment' => $payment,
            'contract' => $contract,
            'client' => $client,
            'plan' => $plan,
            'generated_at' => now(),
            'generated_by' => auth()->user()?->name ?? 'Sistema',
        ];
    }

    /**
     * Crear recordatorio interno para un pago pendiente (RF-PAG-12)
     */
    public function createReminder(Payment $payment, string $mensaje, User $creator): bool
    {
        // RN-17: recordatorios solo para pagos pendientes
        if (! $payment->isPending()) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden crear recordatorios para pagos pendientes.',
            ]);
        }

        DB::table('recordatorios_pago')->insert([
            'pago_id' => $payment->getKey(),
            'creado_por' => $creator->getKey(),
            'fecha_recordatorio' => now(),
            'mensaje' => $mensaje,
            'estado' => 'PENDIENTE',
            'atendido_en' => null,
        ]);

        return true;
    }

    /**
     * Obtener recordatorios de un pago (RF-PAG-12)
     */
    public function getReminders(Payment $payment): array
    {
        return DB::table('recordatorios_pago as r')
            ->leftJoin('usuarios as u', 'u.usuario_id', '=', 'r.creado_por')
            ->where('r.pago_id', $payment->getKey())
            ->select('r.*', 'u.nombre_usuario as creado_por_nombre')
            ->orderByDesc('r.fecha_recordatorio')
            ->get()
            ->toArray();
    }

    /**
     * Marcar recordatorio como atendido
     */
    public function markReminderAsAttended(int $recordatorioId): bool
    {
        return DB::table('recordatorios_pago')
            ->where('recordatorio_id', $recordatorioId)
            ->update([
                'estado' => 'ATENDIDO',
                'atendido_en' => now(),
            ]) > 0;
    }

    /**
     * Actualizar estados de pagos vencidos (RF-PAG-11)
     * Debería ejecutarse automáticamente vía scheduled job
     */
    public function updateOverdueStatuses(): int
    {
        return $this->payments->updateOverdueStatuses();
    }

    /**
     * Obtener estadísticas de pagos para dashboard
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $byStatus = $this->payments->countByStatus();
        $critical = $this->payments->countCritical();

        return array_merge($byStatus, $critical, [
            'total' => array_sum($byStatus),
        ]);
    }

    /**
     * Obtener estadísticas de pagos para un mes específico
     * @param string $month Formato YYYY-MM
     * @return array<string, mixed>
     */
    public function getStatisticsForMonth(string $month): array
    {
        $byStatus = $this->payments->countByStatusForMonth($month);

        return array_merge($byStatus, [
            'total' => array_sum($byStatus),
            'month' => $month,
        ]);
    }

    /**
     * Obtener estadísticas por método de pago (RF-PAG-16, RF-PAG-17)
     * @return array<string, mixed>
     */
    public function getPaymentMethodStats(): array
    {
        $counts = $this->payments->countByPaymentMethod();
        $mostUsed = $this->payments->getMostUsedPaymentMethod();

        return [
            'counts' => $counts,
            'most_used' => $mostUsed,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Filtrar atributos permitidos para mass assignment
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'contrato_id', 'codigo', 'periodo_servicio', 'fecha_vencimiento',
            'fecha_pago', 'monto', 'metodo_pago', 'referencia_externa',
            'estado', 'constancia_ruta', 'observaciones',
        ]);
    }
}
