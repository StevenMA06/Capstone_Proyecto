<?php

namespace App\Repositories;

use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ContractRepository
{
    public const SALES_ASSISTANT_CARGO = 'Asistente de Ventas';

    public function finalizeExpired(string $today): int
    {
        // Obtener contratos que serán finalizados
        $contractsToFinalize = Contract::query()
            ->where('estado', 'ACTIVO')
            ->where('fecha_fin', '<', $today)
            ->get();

        foreach ($contractsToFinalize as $contract) {
            DB::transaction(function () use ($contract, $today) {
                // Actualizar estado del contrato
                $contract->update(['estado' => 'FINALIZADO']);

                // Anular pagos pendientes o vencidos que quedan después de la finalización
                $anulatedCount = DB::table('pagos')
                    ->where('contrato_id', $contract->getKey())
                    ->whereIn('estado', ['PENDIENTE', 'VENCIDO'])
                    ->update([
                        'estado' => 'ANULADO',
                        'observaciones' => DB::raw(
                            "CONCAT(COALESCE(observaciones, ''), '\n[{$today}] Anulado automáticamente por finalización del contrato.')"
                        ),
                    ]);

                if ($anulatedCount > 0) {
                    \Log::info("Anulados {$anulatedCount} pagos del contrato {$contract->codigo} por finalización automática");
                }
            });
        }

        return $contractsToFinalize->count();
    }

    public function search(array $filters): LengthAwarePaginator
    {
        $search = trim($filters['search'] ?? '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $query = $this->details()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw("contratos.codigo LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("clientes.numero_documento LIKE ? ESCAPE '!'", [$pattern])))
            ->when($filters['plan_id'] ?? null, fn ($q, $id) => $q->where('contratos.plan_id', $id))
            ->when($filters['estado'] ?? null, fn ($q, $state) => $q->where('contratos.estado', $state))
            ->when($filters['desde'] ?? null, fn ($q, $date) => $q->where('contratos.fecha_inicio', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($q, $date) => $q->where('contratos.fecha_inicio', '<=', $date));

        match ($filters['orden'] ?? 'recientes') {
            'plan_desc' => $query->orderByDesc('planes_servicio.nombre')->orderByDesc('contratos.contrato_id'),
            'estado_desc' => $query->orderByDesc('contratos.estado')->orderByDesc('contratos.contrato_id'),
            default => $query->orderByDesc('contratos.contrato_id'),
        };

        return $query->paginate(15)->withQueryString();
    }

    public function findDetailed(int $id): object
    {
        return $this->details()->where('contratos.contrato_id', $id)->first() ?? abort(404);
    }

    public function find(int $id, bool $lock = false): object
    {
        $query = Contract::query()->whereKey($id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function create(array $data): int
    {
        return Contract::create($data)->getKey();
    }

    public function update(int $id, array $data): void
    {
        Contract::query()->whereKey($id)->update($data);
    }

    public function activeClient(int $id): ?object
    {
        return DB::table('clientes')->where('cliente_id', $id)->lockForUpdate()->first();
    }

    public function activeZone(int $id): ?object
    {
        return DB::table('zonas_cobertura')->where('zona_id', $id)->lockForUpdate()->first();
    }

    public function activeEmployee(int $id): ?object
    {
        return $this->salesAssistants()->where('empleado_id', $id)->lockForUpdate()->first();
    }

    public function hasPlanCoverage(int $planId): bool
    {
        return DB::table('plan_zona_cobertura')->where('plan_id', $planId)->exists();
    }

    public function planCoversZone(int $planId, int $zoneId): bool
    {
        return DB::table('plan_zona_cobertura')->where('plan_id', $planId)->where('zona_id', $zoneId)->where('estado', 'ACTIVO')->exists();
    }

    public function formOptions(): array
    {
        return [
            'clients' => DB::table('clientes')->where('estado', 'ACTIVO')->orderBy('nombres_razon_social')->get(),
            'zones' => DB::table('zonas_cobertura')->where('estado', 'ACTIVA')->orderBy('nombre')->get(),
            'employees' => $this->salesAssistants()->orderBy('nombres')->orderBy('apellidos')->get(),
            'coverage' => DB::table('plan_zona_cobertura')->get(['plan_id', 'zona_id', 'estado']),
        ];
    }

    public function allPlans()
    {
        return DB::table('planes_servicio')->orderBy('nombre')->get();
    }

    public function option(string $collection, int $id): ?object
    {
        if ($collection === 'employees') {
            return $this->salesAssistants()->where('empleado_id', $id)->first();
        }

        [$table, $key] = match ($collection) {
            'plans' => ['planes_servicio', 'plan_id'],
            'clients' => ['clientes', 'cliente_id'],
            'zones' => ['zonas_cobertura', 'zona_id'],
        };

        return DB::table($table)->where($key, $id)->first();
    }

    private function salesAssistants(): Builder
    {
        return DB::table('empleados')->where('estado', 'ACTIVO')
            ->whereRaw('LOWER(TRIM(cargo)) = ?', [mb_strtolower(self::SALES_ASSISTANT_CARGO)]);
    }

    private function details()
    {
        return DB::table('contratos')
            ->join('clientes', 'clientes.cliente_id', '=', 'contratos.cliente_id')
            ->join('planes_servicio', 'planes_servicio.plan_id', '=', 'contratos.plan_id')
            ->join('zonas_cobertura', 'zonas_cobertura.zona_id', '=', 'contratos.zona_id')
            ->join('empleados', 'empleados.empleado_id', '=', 'contratos.responsable_id')
            ->leftJoin('periodos_contractuales as periodo_vigente', 'periodo_vigente.periodo_id', '=', 'contratos.periodo_actual')
            ->select('contratos.*', 'clientes.numero_documento', 'clientes.tipo_documento', 'clientes.nombres_razon_social', 'clientes.apellidos', 'clientes.distrito as cliente_distrito', 'planes_servicio.nombre as plan_nombre', 'planes_servicio.numero_plan', 'zonas_cobertura.nombre as zona_nombre', 'empleados.nombres as responsable_nombres', 'empleados.apellidos as responsable_apellidos', 'empleados.cargo as responsable_cargo', 'periodo_vigente.motivo_cancelacion', 'periodo_vigente.fecha_cancelacion', 'periodo_vigente.origen_cancelacion');
    }

    /**
     * Obtener contratos cancelados que pueden ser reactivados
     */
    public function getCancelledContracts(): LengthAwarePaginator
    {
        return Contract::with(['client', 'plan'])
            ->where('estado', 'CANCELADO')
            ->orderByDesc('actualizado_en')
            ->paginate(15);
    }

    /**
     * Verificar si un contrato puede ser reactivado
     */
    public function canBeReactivated(int $id): bool
    {
        $contract = $this->find($id);

        return $contract->estado === 'CANCELADO';
    }
}
