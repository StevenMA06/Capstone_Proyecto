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

        return $query->orderByDesc('creado_en')
            ->orderByDesc('pago_id')
            ->paginate(15)
            ->withQueryString();
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
}
