<?php

namespace App\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SaleRepository
{
    public function confirm(object $contract): void
    {
        DB::table('ventas')->updateOrInsert(
            ['contrato_id' => $contract->contrato_id],
            [
                'cliente_id' => $contract->cliente_id,
                'responsable_id' => $contract->responsable_id,
                'fecha_venta' => now(),
                'monto' => $contract->precio_contratado,
                'estado' => 'CONFIRMADA',
            ],
        );
    }

    public function annul(int $contractId): void
    {
        DB::table('ventas')->where('contrato_id', $contractId)->update(['estado' => 'ANULADA']);
    }

    public function employees()
    {
        return DB::table('empleados')->whereIn('empleado_id', DB::table('ventas')->select('responsable_id'))
            ->orderBy('nombres')->orderBy('apellidos')->get(['empleado_id', 'nombres', 'apellidos']);
    }

    public function query(array $filters, bool $includeStates = true): Builder
    {
        $query = DB::table('ventas as v')
            ->join('contratos as c', 'c.contrato_id', '=', 'v.contrato_id')
            ->join('clientes as cl', 'cl.cliente_id', '=', 'v.cliente_id')
            ->join('empleados as e', 'e.empleado_id', '=', 'v.responsable_id');

        $client = trim($filters['cliente'] ?? '');
        if ($client !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $client).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw("cl.nombres_razon_social LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("cl.apellidos LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("CONCAT(cl.nombres_razon_social, ' ', COALESCE(cl.apellidos, '')) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($filters['desde'] ?? null) {
            $query->where('v.fecha_venta', '>=', $filters['desde'].' 00:00:00');
        }
        if ($filters['hasta'] ?? null) {
            $query->where('v.fecha_venta', '<', CarbonImmutable::parse($filters['hasta'])->addDay()->format('Y-m-d').' 00:00:00');
        }
        if ($filters['empleado_id'] ?? null) {
            $query->where('v.responsable_id', $filters['empleado_id']);
        }
        if ($includeStates && isset($filters['estados'])) {
            $query->whereIn('v.estado', $filters['estados']);
        }

        return $query;
    }

    public function results(array $filters)
    {
        return $this->query($filters)
            ->select('v.venta_id', 'v.fecha_venta', 'v.monto', 'v.estado as venta_estado',
                'c.contrato_id', 'c.codigo', 'c.estado as contrato_estado',
                'cl.nombres_razon_social', 'cl.apellidos', 'cl.numero_documento',
                'e.nombres as empleado_nombres', 'e.apellidos as empleado_apellidos')
            ->orderByDesc('v.fecha_venta')->orderByDesc('v.venta_id')
            ->paginate(12)->withQueryString();
    }

    public function summary(array $filters): object
    {
        return $this->query($filters)->selectRaw('COUNT(*) as quantity, COALESCE(SUM(v.monto), 0) as amount')->first();
    }

    public function byEmployee(array $filters)
    {
        return $this->query($filters)
            ->select('e.empleado_id', 'e.nombres', 'e.apellidos')
            ->selectRaw('COUNT(*) as quantity')
            ->groupBy('e.empleado_id', 'e.nombres', 'e.apellidos')
            ->orderByDesc('quantity')->orderBy('e.nombres')->get();
    }

    public function byState(array $filters)
    {
        return $this->query($filters, false)
            ->select('v.estado')->selectRaw('COUNT(*) as quantity')
            ->groupBy('v.estado')->pluck('quantity', 'estado');
    }

    public function byPeriod(array $filters, bool $daily)
    {
        $period = $daily ? 'DATE(v.fecha_venta)' : "DATE_FORMAT(v.fecha_venta, '%Y-%m')";

        return $this->query($filters)
            ->selectRaw("$period as period, COUNT(*) as quantity, SUM(v.monto) as amount")
            ->groupByRaw($period)->orderBy('period')->get();
    }
}
