<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function __construct(private InventoryRepository $inventory) {}

    public function clients(): int
    {
        return DB::table('clientes')->count();
    }

    public function management(): array
    {
        $now = now();
        $start = $now->copy()->startOfMonth()->subMonths(5);
        $totals = DB::table('ventas')->where('estado', 'CONFIRMADA')->whereBetween('fecha_venta', [$start, $now])
            ->selectRaw("DATE_FORMAT(fecha_venta, '%Y-%m') as month, SUM(monto) as total")
            ->groupByRaw("DATE_FORMAT(fecha_venta, '%Y-%m')")->pluck('total', 'month');
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $months[] = ['label' => $month->locale('es')->translatedFormat('M Y'), 'total' => (float) ($totals[$month->format('Y-m')] ?? 0)];
        }

        $equipment = $this->inventory->equipment()->get();
        $materials = $this->inventory->materials()->get();
        $topPlans = DB::table('ventas as v')
            ->join('contratos as c', 'c.contrato_id', '=', 'v.contrato_id')
            ->join('planes_servicio as p', 'p.plan_id', '=', 'c.plan_id')
            ->where('v.estado', 'CONFIRMADA')->whereBetween('v.fecha_venta', [$start, $now])
            ->select('p.plan_id', 'p.numero_plan', 'p.nombre')->selectRaw('COUNT(*) as total')
            ->groupBy('p.plan_id', 'p.numero_plan', 'p.nombre')
            ->orderByDesc('total')->orderBy('p.plan_id')->limit(5)->get();

        return [
            'contracts' => DB::table('contratos')->where('estado', 'ACTIVO')->count(),
            'payments' => DB::table('pagos')->whereIn('estado', ['PENDIENTE', 'VENCIDO'])->count(),
            'installations' => DB::table('pedidos_instalacion')->where('estado', 'EN_PROCESO')->count(),
            'equipment' => $equipment,
            'materials' => $materials,
            'equipmentLow' => $equipment->filter(fn ($item) => $item->stock_actual <= $item->stock_minimo)->count(),
            'materialsLow' => $materials->filter(fn ($item) => $item->stock_actual <= $item->stock_minimo)->count(),
            'months' => $months,
            'topPlans' => $topPlans,
            'topPlansMax' => max(1, (int) $topPlans->max('total')),
            'salesTotal' => array_sum(array_column($months, 'total')),
            'salesMax' => max(1, ...array_column($months, 'total')),
        ];
    }
}
