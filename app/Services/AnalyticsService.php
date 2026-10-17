<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

class AnalyticsService
{
    public const PROCESSES = ['clientes'=>'Clientes', 'contratos'=>'Contratos', 'pagos'=>'Pagos', 'instalaciones'=>'Instalaciones', 'inventario'=>'Inventario'];
    private const SOURCES = [
        'clientes'=>['clientes','creado_en','clientes_altas'],
        'contratos'=>['contratos','fecha_registro','contratos_registrados'],
        'pagos'=>['pagos','fecha_pago','pagos_recaudados'],
        'instalaciones'=>['pedidos_instalacion','fecha_solicitud','instalaciones_estado'],
        'inventario'=>['movimientos_inventario','fecha_movimiento','kardex_movimientos'],
    ];

    public function dashboard(string $from, string $to, string $process): array
    {
        $start = CarbonImmutable::parse($from); $end = CarbonImmutable::parse($to);
        $days = (int) $start->diffInDays($end) + 1;
        $previousFrom = $start->subDays($days)->toDateString();
        $previousTo = $start->subDay()->toDateString();
        $items = [];
        foreach (self::PROCESSES as $key=>$label) {
            if ($process !== 'all' && $process !== $key) continue;
            [$table,$date,$report] = self::SOURCES[$key];
            $query = fn($a,$b) => DB::table($table)->whereBetween($date, [$a.' 00:00:00',$b.' 23:59:59']);
            $current = $query($from,$to);
            if ($key === 'pagos') $current->where('estado','REALIZADO');
            $previous = $query($previousFrom,$previousTo);
            if ($key === 'pagos') $previous->where('estado','REALIZADO');
            $count = (clone $current)->count(); $before = $previous->count();
            $monthly = (clone $current)->selectRaw("DATE_FORMAT($date, '%Y-%m') AS mes, COUNT(*) AS cantidad")->groupBy('mes')->orderBy('mes')->pluck('cantidad','mes')->all();
            $trend = [];
            for ($month=$start->startOfMonth(); $month <= $end; $month=$month->addMonth()) {
                $trend[$month->format('Y-m')] = (int) ($monthly[$month->format('Y-m')] ?? 0);
            }
            $states = [];
            if ($key !== 'inventario') {
                // Current portfolio is deliberately separate from period-based activity.
                $states = DB::table($table)->select('estado')->selectRaw('COUNT(*) AS cantidad')->groupBy('estado')->orderBy('estado')->pluck('cantidad','estado')->all();
            } else {
                $states = DB::table('equipos')->select('estado')->selectRaw('COUNT(*) AS cantidad')->groupBy('estado')->orderBy('estado')->pluck('cantidad','estado')->all();
            }
            $items[$key] = compact('label','report','count','before','trend','states');
        }
        $quality = $this->processQuality($from, $to, $process);
        $materials = ($process==='all' || $process==='inventario') ? DB::table('materiales')->where('estado','ACTIVO')->orderByRaw('(stock_actual <= stock_minimo) DESC')->orderBy('nombre')->limit(8)
            ->select(['codigo','nombre','stock_actual','stock_minimo'])
            ->selectRaw('CASE WHEN inventario_en_empaque = 1 THEN unidad_empaque ELSE unidad_base END as unidad_medida')->get() : collect();
        return compact('materials','items','quality','previousFrom','previousTo');
    }

    private function processQuality(string $from, string $to, string $process): array
    {
        $definitions = [
            'clientes' => ['Clientes', 'clientes', 'creado_en', 'clientes_altas', fn ($query) => $query->whereIn('estado', ['ACTIVO', 'INACTIVO']), 'Estado válido del cliente.'],
            'contratos' => ['Contratos', 'contratos', 'fecha_registro', 'contratos_registrados', fn ($query) => $query->where('estado', 'ACTIVO')->where('firma_revision', 'VERIFICADA'), 'Contrato activo con evidencia verificada.'],
            'pagos' => ['Pagos', 'pagos', 'creado_en', 'pagos_recaudados', fn ($query) => $query->where('estado', 'REALIZADO'), 'Pago registrado como realizado.'],
            'instalaciones' => ['Instalaciones', 'pedidos_instalacion', 'fecha_solicitud', 'instalaciones_estado', fn ($query) => $query->where('estado', 'COMPLETADO'), 'Instalación finalizada por el técnico.'],
            'inventario' => ['Inventario', 'movimientos_inventario', 'fecha_movimiento', 'kardex_movimientos', fn ($query) => $query->whereNotNull('responsable_id')->where(fn ($items) => $items->whereNotNull('equipo_id')->orWhereNotNull('material_id')), 'Movimiento con responsable y artículo asociado.'],
        ];

        $quality = [];
        foreach ($definitions as $key => [$label, $table, $date, $report, $completed, $criterion]) {
            if ($process !== 'all' && $process !== $key) continue;
            $records = DB::table($table)->whereBetween($date, [$from.' 00:00:00', $to.' 23:59:59']);
            $total = (clone $records)->count();
            $completedRecords = $completed(clone $records)->count();
            $quality[$key] = [
                'label' => $label,
                'completed' => $completedRecords,
                'total' => $total,
                'percentage' => $total ? round($completedRecords / $total * 100, 1) : null,
                'criterion' => $criterion,
                'module' => $key,
                'report' => $report,
            ];
        }
        return $quality;
    }
}

