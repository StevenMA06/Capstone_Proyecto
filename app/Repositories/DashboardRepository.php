<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class DashboardRepository
{
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

        return [
            'contracts' => DB::table('contratos')->where('estado', 'ACTIVO')->count(),
            'payments' => DB::table('pagos')->whereIn('estado', ['PENDIENTE', 'VENCIDO'])->count(),
            'installations' => DB::table('pedidos_instalacion')->whereIn('estado', ['PROGRAMADO', 'REPROGRAMADO'])->count(),
            'equipment' => DB::table('equipos')->where('estado', 'DISPONIBLE')->count(),
            'materials' => DB::table('materiales')->where('estado', 'ACTIVO')->whereColumn('stock_actual', '<=', 'stock_minimo')->count(),
            'months' => $months,
            'salesTotal' => array_sum(array_column($months, 'total')),
            'salesMax' => max(1, ...array_column($months, 'total')),
        ];
    }
}
