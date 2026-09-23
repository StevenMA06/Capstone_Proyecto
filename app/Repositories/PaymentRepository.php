<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    /**
     * Búsqueda avanzada de pagos con múltiples filtros
     * @param array<string, mixed> $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with(['contract.client', 'contract.plan', 'registeredBy']);

        // Filtro por documento del cliente o nombre (RF-PAG-08)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('contract.client', function ($q) use ($search): void {
                $q->where('numero_documento', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT_WS(' ', nombres_razon_social, apellidos) LIKE ?", ['%'.$search.'%']);
            });
        }

        // Filtro por código de contrato (RF-PAG-06)
        if (! empty($filters['contract'])) {
            $query->whereHas('contract', function ($q) use ($filters): void {
                $q->where('codigo', 'like', '%'.$filters['contract'].'%');
            });
        }

        // Filtro por estado del pago (RF-PAG-07)
        if (! empty($filters['status'])) {
            $query->where('estado', $filters['status']);
        }

        // Filtro por mes (RF-PAG - nuevo)
        if (! empty($filters['month'])) {
            $month = $filters['month'];
            $firstDay = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $lastDay = $firstDay->copy()->endOfMonth();
            
            $query->whereBetween('fecha_vencimiento', [$firstDay, $lastDay]);
        }

        // Filtro por rango de fechas
        if (! empty($filters['from'])) {
            $query->where('fecha_vencimiento', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('fecha_vencimiento', '<=', $filters['to']);
        }

        // Filtro: próximos a vencer (RF-PAG-10)
        if (! empty($filters['due_soon'])) {
            $days = (int) ($filters['due_soon_days'] ?? 7);
            $query->where('estado', 'PENDIENTE')
                ->whereBetween('fecha_vencimiento', [
                    now()->startOfDay(),
                    now()->addDays($days)->endOfDay(),
                ]);
        }

        // Filtro: vencidos (RF-PAG-11)
        if (! empty($filters['overdue'])) {
            $query->where('estado', 'PENDIENTE')
                ->where('fecha_vencimiento', '<', now()->startOfDay());
        }

        // Ordenamiento mejorado: primero por estado (prioridad), luego por fecha de vencimiento
        // Orden de prioridad de estados: PENDIENTE, VENCIDO, ANULADO, REALIZADO
        $query->orderByRaw("
            CASE estado 
                WHEN 'PENDIENTE' THEN 1
                WHEN 'VENCIDO' THEN 2
                WHEN 'ANULADO' THEN 3
                WHEN 'REALIZADO' THEN 4
                ELSE 5
            END
        ")
        ->orderBy('fecha_vencimiento', 'asc')
        ->orderByDesc('pago_id');

        return $query->paginate(15)->withQueryString();
    }

    /** @param array<string, mixed> $attributes */
    public function update(Payment $payment, array $attributes): Payment
    {
        $payment->fill($attributes)->save();

        return $payment->refresh();
    }

    /**
     * Anular un pago (RF-PAG - no elimina, cambia estado)
     */
    public function cancel(Payment $payment, string $observacion = null): Payment
    {
        $payment->estado = 'ANULADO';
        if ($observacion) {
            $payment->observaciones = $observacion;
        }
        $payment->save();

        return $payment->refresh();
    }

    /**
     * Obtener pagos de un contrato específico (RF-PAG-06)
     */
    public function findByContract(Contract $contract): LengthAwarePaginator
    {
        return Payment::with(['contract', 'registeredBy'])
            ->where('contrato_id', $contract->getKey())
            ->orderByDesc('creado_en')
            ->orderByDesc('pago_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Obtener pagos de un cliente específico (RF-PAG-05)
     */
    public function findByClient(Client $client): LengthAwarePaginator
    {
        return Payment::with(['contract', 'registeredBy'])
            ->whereHas('contract', function ($q) use ($client): void {
                $q->where('cliente_id', $client->getKey());
            })
            ->orderByDesc('creado_en')
            ->orderByDesc('pago_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Obtener pagos pendientes
     */
    public function pending(): LengthAwarePaginator
    {
        return Payment::with(['contract', 'registeredBy'])
            ->pending()
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('pago_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Obtener pagos vencidos (RF-PAG-11)
     */
    public function overdue(): LengthAwarePaginator
    {
        return Payment::with(['contract', 'registeredBy'])
            ->where('estado', 'PENDIENTE')
            ->where('fecha_vencimiento', '<', now()->startOfDay())
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('pago_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Obtener pagos próximos a vencer en N días (RF-PAG-10)
     */
    public function dueIn(int $days = 7): LengthAwarePaginator
    {
        return Payment::with(['contract', 'registeredBy'])
            ->dueInDays($days)
            ->orderBy('fecha_vencimiento')
            ->orderByDesc('pago_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Contar pagos por estado para dashboard
     */
    public function countByStatus(): array
    {
        $counts = DB::table('pagos')
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'pendiente' => $counts['PENDIENTE'] ?? 0,
            'realizado' => $counts['REALIZADO'] ?? 0,
            'vencido' => $counts['VENCIDO'] ?? 0,
            'anulado' => $counts['ANULADO'] ?? 0,
        ];
    }

    /**
     * Contar pagos por estado para un mes específico
     * @param string $month Formato YYYY-MM
     * @return array<string, int>
     */
    public function countByStatusForMonth(string $month): array
    {
        // Filtrar por periodo_servicio en lugar de fecha_vencimiento
        // Esto permite contar pagos del mes correcto según su período de facturación
        $counts = DB::table('pagos')
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->where('periodo_servicio', $month)
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'PENDIENTE' => $counts['PENDIENTE'] ?? 0,
            'REALIZADO' => $counts['REALIZADO'] ?? 0,
            'VENCIDO' => $counts['VENCIDO'] ?? 0,
            'IMPAGO' => $counts['IMPAGO'] ?? 0,
            'ANULADO' => $counts['ANULADO'] ?? 0,
        ];
    }

    /**
     * Contar pagos pendientes y vencidos para alertas
     */
    public function countCritical(): array
    {
        $pending = DB::table('pagos')
            ->where('estado', 'PENDIENTE')
            ->count();

        $overdue = DB::table('pagos')
            ->where('estado', 'PENDIENTE')
            ->where('fecha_vencimiento', '<', now()->startOfDay())
            ->count();

        $dueSoon = DB::table('pagos')
            ->where('estado', 'PENDIENTE')
            ->whereBetween('fecha_vencimiento', [
                now()->startOfDay(),
                now()->addDays(7)->endOfDay(),
            ])
            ->count();

        return [
            'pending' => $pending,
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
        ];
    }

    /**
     * Actualizar estados de pagos vencidos automáticamente
     * Este método debería ser llamado por un scheduled job
     */
    public function updateOverdueStatuses(): int
    {
        return DB::table('pagos')
            ->where('estado', 'PENDIENTE')
            ->where('fecha_vencimiento', '<', now()->startOfDay())
            ->update(['estado' => 'VENCIDO']);
    }

    /**
     * Generar código único para pago
     */
    public function generateCode(): string
    {
        do {
            $code = 'PAG-'.now()->format('Ymd').'-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));
            $exists = DB::table('pagos')->where('codigo', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Generar pagos mensuales automáticamente para un contrato
     * Basado en fecha_inicio y duracion_meses del contrato
     * 
     * @param Contract $contract
     * @param int $registradoPor ID del usuario que genera los pagos (sistema o usuario)
     * @return array Array de Payment creados
     */
    public function generateMonthlyPayments(Contract $contract, int $registradoPor): array
    {
        $payments = [];
        $fechaInicio = \Carbon\Carbon::parse($contract->fecha_inicio);
        $duracionMeses = (int) $contract->duracion_meses;
        $montoPorPeriodo = $contract->precio_contratado;

        for ($mes = 0; $mes < $duracionMeses; $mes++) {
            // Calcular fecha de vencimiento: día del inicio + mes actual
            $fechaVencimiento = $fechaInicio->copy()->addMonthsNoOverflow($mes);
            
            // Formato del periodo: YYYY-MM
            $periodoServicio = $fechaVencimiento->format('Y-m');

            // Verificar si ya existe un pago para este período
            $existingPayment = Payment::where('contrato_id', $contract->getKey())
                ->where('periodo_servicio', $periodoServicio)
                ->first();

            if ($existingPayment) {
                // Ya existe, saltarlo
                continue;
            }

            // Crear el pago
            $payment = $this->create([
                'contrato_id' => $contract->getKey(),
                'codigo' => $this->generateCode(),
                'periodo_servicio' => $periodoServicio,
                'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                'monto' => $montoPorPeriodo,
                'estado' => 'PENDIENTE',
                'registrado_por' => $registradoPor,
                'observaciones' => 'Pago generado automáticamente para período '.$periodoServicio,
            ]);

            $payments[] = $payment;
        }

        return $payments;
    }

    /**
     * Generar el siguiente pago mensual para un contrato activo
     * Útil cuando el sistema genera pagos uno a uno cada mes
     * 
     * @param Contract $contract
     * @param int $registradoPor
     * @return Payment|null
     */
    public function generateNextMonthlyPayment(Contract $contract, int $registradoPor): ?Payment
    {
        // Obtener el último periodo de servicio registrado
        $lastPayment = Payment::where('contrato_id', $contract->getKey())
            ->whereNotNull('periodo_servicio')
            ->orderByDesc('periodo_servicio')
            ->first();

        $fechaInicio = \Carbon\Carbon::parse($contract->fecha_inicio);
        $fechaFin = \Carbon\Carbon::parse($contract->fecha_fin);

        // Determinar el próximo período
        if ($lastPayment) {
            // Calcular el siguiente mes
            $ultimoPeriodo = \Carbon\Carbon::createFromFormat('Y-m', $lastPayment->periodo_servicio);
            $proximoPeriodo = $ultimoPeriodo->copy()->addMonth();
        } else {
            // Primer pago
            $proximoPeriodo = $fechaInicio->copy();
        }

        // Verificar que no supere la fecha fin del contrato
        if ($proximoPeriodo->greaterThan($fechaFin)) {
            return null; // Ya se generaron todos los pagos
        }

        $periodoServicio = $proximoPeriodo->format('Y-m');

        // Verificar que no exista ya
        $existing = Payment::where('contrato_id', $contract->getKey())
            ->where('periodo_servicio', $periodoServicio)
            ->first();

        if ($existing) {
            return null;
        }

        // Crear el pago
        return $this->create([
            'contrato_id' => $contract->getKey(),
            'codigo' => $this->generateCode(),
            'periodo_servicio' => $periodoServicio,
            'fecha_vencimiento' => $proximoPeriodo->format('Y-m-d'),
            'monto' => $contract->precio_contratado,
            'estado' => 'PENDIENTE',
            'registrado_por' => $registradoPor,
            'observaciones' => 'Pago generado automáticamente para período '.$periodoServicio,
        ]);
    }

    /**
     * Contar cuántos pagos se han generado para un contrato
     */
    public function countPaymentsByContract(Contract $contract): int
    {
        return Payment::where('contrato_id', $contract->getKey())->count();
    }

    /**
     * Verificar si todos los pagos del contrato fueron generados
     */
    public function allPaymentsGenerated(Contract $contract): bool
    {
        $totalEsperado = (int) $contract->duracion_meses;
        $totalGenerado = $this->countPaymentsByContract($contract);

        return $totalGenerado >= $totalEsperado;
    }

    /**
     * Contar pagos por método de pago (RF-PAG-16)
     * @return array<string, int>
     */
    public function countByPaymentMethod(): array
    {
        $counts = DB::table('pagos')
            ->select('metodo_pago', DB::raw('COUNT(*) as total'))
            ->where('estado', 'REALIZADO')
            ->whereNotNull('metodo_pago')
            ->groupBy('metodo_pago')
            ->pluck('total', 'metodo_pago')
            ->toArray();

        // Asegurar que todos los métodos aparezcan
        $methods = ['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'];
        $result = [];
        
        foreach ($methods as $method) {
            $result[$method] = $counts[$method] ?? 0;
        }

        return $result;
    }

    /**
     * Obtener el método de pago más utilizado (RF-PAG-17)
     * @return array{method: string|null, count: int}
     */
    public function getMostUsedPaymentMethod(): array
    {
        $counts = $this->countByPaymentMethod();
        
        if (empty($counts)) {
            return ['method' => null, 'count' => 0];
        }

        $maxCount = max($counts);
        $mostUsed = array_search($maxCount, $counts, true);

        return [
            'method' => $mostUsed ?: null,
            'count' => $maxCount,
        ];
    }

    /**
     * Contar pagos por estado de todos los meses (estadísticas globales)
     */
    public function countByStatusGlobal(): array
    {
        $counts = DB::table('pagos')
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'PENDIENTE' => $counts['PENDIENTE'] ?? 0,
            'REALIZADO' => $counts['REALIZADO'] ?? 0,
            'VENCIDO' => $counts['VENCIDO'] ?? 0,
            'IMPAGO' => $counts['IMPAGO'] ?? 0,
            'ANULADO' => $counts['ANULADO'] ?? 0,
        ];
    }
}
