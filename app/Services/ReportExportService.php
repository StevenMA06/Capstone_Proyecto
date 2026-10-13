<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReportExportService
{
    public const OFFICE_TYPES = [
        'clientes_altas', 'clientes_activos', 'clientes_inactivos', 'clientes_mes',
        'contratos_registrados', 'contratos_estado', 'contratos_vencer', 'ventas_mes',
        'pagos_recaudados', 'pagos_pendientes', 'pagos_vencidos',
    ];

    public const MODULES = [
        'clientes' => ['Clientes', ['clientes_altas', 'clientes_activos', 'clientes_inactivos', 'clientes_mes']],
        'contratos' => ['Contratos', ['contratos_registrados', 'contratos_estado', 'contratos_vencer', 'ventas_mes']],
        'pagos' => ['Pagos', ['pagos_recaudados', 'pagos_pendientes', 'pagos_vencidos']],
        'instalaciones' => ['Instalaciones', ['instalaciones_estado', 'instalaciones_agenda']],
        'inventario' => ['Inventario', ['materiales_existencias', 'materiales_bajo_minimo', 'equipos_estado', 'kardex_movimientos']],
    ];

    public const DEFINITIONS = [
        'clientes_altas' => ['Clientes', 'Clientes registrados', 'Fecha de registro', ['Código','Cliente','Documento','Distrito','Fecha de registro','Estado']],
        'clientes_activos' => ['Clientes', 'Clientes activos', 'Corte actual', ['Código','Cliente','Documento','Distrito','Estado']],
        'clientes_inactivos' => ['Clientes', 'Clientes inactivos', 'Corte actual', ['Código','Cliente','Documento','Distrito','Estado']],
        'clientes_mes' => ['Clientes', 'Nuevos clientes por mes', 'Mes de registro', ['Mes','Nuevos clientes']],
        'contratos_registrados' => ['Contratos', 'Contratos registrados', 'Fecha de registro', ['Contrato','Cliente','Fecha de registro','Inicio','Fin','Estado']],
        'contratos_estado' => ['Contratos', 'Contratos por estado', 'Corte actual', ['Estado','Cantidad de contratos']],
        'contratos_vencer' => ['Contratos', 'Contratos activos por vencer', 'Fecha de fin', ['Contrato','Cliente','Plan','Fecha de fin','Precio mensual','Estado']],
        'ventas_mes' => ['Contratos', 'Ventas por mes', 'Fecha de venta', ['Mes','Ventas confirmadas','Monto vendido']],
        'pagos_recaudados' => ['Pagos', 'Pagos recaudados', 'Fecha de pago', ['Pago','Cliente','Contrato','Fecha de pago','Método','Monto']],
        'pagos_pendientes' => ['Pagos', 'Pagos pendientes por cobrar', 'Fecha de vencimiento', ['Pago','Cliente','Contrato','Vencimiento','Días de atraso','Monto','Estado']],
        'pagos_vencidos' => ['Pagos', 'Pagos vencidos', 'Fecha de vencimiento', ['Pago','Cliente','Contrato','Vencimiento','Días de atraso','Monto','Estado']],
        'instalaciones_estado' => ['Instalaciones', 'Pedidos de instalación por estado', 'Fecha de solicitud', ['Estado','Cantidad de pedidos']],
        'instalaciones_agenda' => ['Instalaciones', 'Agenda de instalaciones', 'Fecha programada', ['Pedido','Cliente','Técnico','Fecha programada','Estado']],
        'materiales_existencias' => ['Inventario', 'Existencias de materiales', 'Corte actual', ['Código','Material','Stock actual','Stock mínimo','Unidad','Estado']],
        'materiales_bajo_minimo' => ['Inventario', 'Materiales bajo el stock mínimo', 'Corte actual', ['Código','Material','Stock actual','Stock mínimo','Unidad','Estado']],
        'equipos_estado' => ['Inventario', 'Disponibilidad de equipos', 'Corte actual', ['Código','Equipo','N.º de serie','Condición','Estado']],
        'kardex_movimientos' => ['Inventario', 'Movimientos de Kardex', 'Fecha de movimiento', ['ID movimiento','Código','Artículo','Movimiento','Fecha','Cantidad','Unidad','ID responsable']],
    ];

    public function modules(bool $manager): array
    {
        return array_filter(self::MODULES, fn ($module) => $manager || array_intersect($module[1], self::OFFICE_TYPES));
    }

    public function reportsForModule(string $module): array
    {
        return array_filter(self::DEFINITIONS, fn ($definition) => $definition[0] === (self::MODULES[$module][0] ?? null));
    }

    public function report(string $type, string $from, string $to, ?int $page = null): array
    {
        [$module, $title, $dateField, $columns] = self::DEFINITIONS[$type];
        $query = match ($type) {
            'clientes_altas' => DB::table('clientes')->whereBetween(DB::raw('DATE(creado_en)'), [$from, $to])
                ->selectRaw("CONCAT('CLI-', cliente_id) AS codigo, TRIM(CONCAT(nombres_razon_social, ' ', COALESCE(apellidos, ''))) AS cliente, CONCAT(tipo_documento, ' ', numero_documento) AS documento, COALESCE(distrito, '—') AS distrito, DATE_FORMAT(creado_en, '%Y-%m-%d') AS fecha, estado")
                ->orderByDesc('creado_en'),
            'clientes_activos', 'clientes_inactivos' => DB::table('clientes')->where('estado', $type === 'clientes_activos' ? 'ACTIVO' : 'INACTIVO')->selectRaw("CONCAT('CLI-', cliente_id) AS codigo, TRIM(CONCAT(nombres_razon_social, ' ', COALESCE(apellidos, ''))) AS cliente, CONCAT(tipo_documento, ' ', numero_documento) AS documento, COALESCE(distrito, '—') AS distrito, estado")->orderBy('cliente_id'),
            'clientes_mes' => DB::table('clientes')->whereBetween(DB::raw('DATE(creado_en)'), [$from, $to])
                ->selectRaw("DATE_FORMAT(creado_en, '%Y-%m') AS mes, COUNT(*) AS cantidad")->groupBy('mes')->orderBy('mes'),
            'contratos_registrados' => DB::table('contratos')->join('clientes','clientes.cliente_id','=','contratos.cliente_id')->whereBetween('contratos.fecha_registro',[$from.' 00:00:00',$to.' 23:59:59'])->select('contratos.codigo','clientes.nombres_razon_social','contratos.fecha_registro','contratos.fecha_inicio','contratos.fecha_fin','contratos.estado')->orderByDesc('contratos.fecha_registro'),
            'contratos_estado' => DB::table('contratos')->select('estado')->selectRaw('COUNT(*) AS cantidad')->groupBy('estado')->orderBy('estado'),
            'contratos_vencer' => DB::table('contratos')->join('clientes', 'clientes.cliente_id', '=', 'contratos.cliente_id')->join('planes_servicio', 'planes_servicio.plan_id', '=', 'contratos.plan_id')
                ->where('contratos.estado', 'ACTIVO')->whereBetween('contratos.fecha_fin', [$from, $to])
                ->select('contratos.codigo', 'clientes.nombres_razon_social AS cliente', 'planes_servicio.nombre AS plan', 'contratos.fecha_fin', 'contratos.precio_contratado', 'contratos.estado')->orderBy('contratos.fecha_fin'),
            'ventas_mes' => DB::table('ventas')->where('estado', 'CONFIRMADA')->whereBetween(DB::raw('DATE(fecha_venta)'), [$from, $to])
                ->selectRaw("DATE_FORMAT(fecha_venta, '%Y-%m') AS mes, COUNT(*) AS cantidad, SUM(monto) AS monto")->groupBy('mes')->orderBy('mes'),
            'pagos_recaudados' => DB::table('pagos')->join('contratos', 'contratos.contrato_id', '=', 'pagos.contrato_id')->join('clientes', 'clientes.cliente_id', '=', 'contratos.cliente_id')
                ->where('pagos.estado', 'REALIZADO')->whereBetween(DB::raw('DATE(pagos.fecha_pago)'), [$from, $to])
                ->select('pagos.codigo', 'clientes.nombres_razon_social AS cliente', 'contratos.codigo AS contrato', 'pagos.fecha_pago', 'pagos.metodo_pago', 'pagos.monto')->orderByDesc('pagos.fecha_pago'),
            'pagos_pendientes', 'pagos_vencidos' => $this->paymentsQuery($type, $from, $to),
            'instalaciones_estado' => DB::table('pedidos_instalacion')->whereBetween(DB::raw('DATE(fecha_solicitud)'), [$from, $to])
                ->select('estado')->selectRaw('COUNT(*) AS cantidad')->groupBy('estado')->orderBy('estado'),
            'instalaciones_agenda' => DB::table('pedidos_instalacion')->join('clientes', 'clientes.cliente_id', '=', 'pedidos_instalacion.cliente_id')->leftJoin('empleados', 'empleados.empleado_id', '=', 'pedidos_instalacion.tecnico_id')
                ->whereNotNull('pedidos_instalacion.fecha_programada')->whereBetween(DB::raw('DATE(pedidos_instalacion.fecha_programada)'), [$from, $to])
                ->select('pedidos_instalacion.codigo', 'clientes.nombres_razon_social AS cliente', DB::raw("COALESCE(CONCAT(empleados.nombres, ' ', empleados.apellidos), 'Sin asignar') AS tecnico"), 'pedidos_instalacion.fecha_programada', 'pedidos_instalacion.estado')->orderBy('pedidos_instalacion.fecha_programada'),
            'materiales_existencias' => DB::table('materiales')->where('estado','ACTIVO')->select('codigo','nombre','stock_actual','stock_minimo')
                ->selectRaw('CASE WHEN inventario_en_empaque = 1 THEN unidad_empaque ELSE unidad_base END as unidad_medida')->addSelect('estado')->orderBy('nombre'),
            'materiales_bajo_minimo' => DB::table('materiales')->where('estado', 'ACTIVO')->whereColumn('stock_actual', '<=', 'stock_minimo')
                ->select('codigo', 'nombre', 'stock_actual', 'stock_minimo')
                ->selectRaw('CASE WHEN inventario_en_empaque = 1 THEN unidad_empaque ELSE unidad_base END as unidad_medida')->addSelect('estado')->orderBy('nombre'),
            'equipos_estado' => DB::table('equipos')->where('estado', '<>', 'INACTIVO')->select('codigo', 'nombre', 'numero_serie', 'condicion', 'estado')->orderBy('estado')->orderBy('nombre'),
            'kardex_movimientos' => DB::table('movimientos_inventario')->leftJoin('equipos', 'equipos.equipo_id', '=', 'movimientos_inventario.equipo_id')->leftJoin('materiales', 'materiales.material_id', '=', 'movimientos_inventario.material_id')->leftJoin('usuarios', 'usuarios.usuario_id', '=', 'movimientos_inventario.responsable_id')
                ->whereBetween(DB::raw('DATE(movimientos_inventario.fecha_movimiento)'), [$from, $to])
                ->select('movimientos_inventario.movimiento_id', DB::raw("COALESCE(equipos.codigo, materiales.codigo) AS codigo"), DB::raw("COALESCE(equipos.nombre, materiales.nombre) AS articulo"), 'movimientos_inventario.tipo_movimiento', 'movimientos_inventario.fecha_movimiento', 'movimientos_inventario.cantidad', DB::raw("COALESCE(CASE WHEN materiales.inventario_en_empaque = 1 THEN materiales.unidad_empaque ELSE materiales.unidad_base END, 'unidad') AS unidad"), 'movimientos_inventario.responsable_id')->orderByDesc('movimientos_inventario.fecha_movimiento'),
            default => throw new \InvalidArgumentException('El tipo de reporte no existe.'),
        };

        $pagination = $page !== null ? $query->paginate(20, ['*'], 'page', $page)->withQueryString() : null;
        $records = $pagination ? collect($pagination->items()) : $query->get();
        $rows = $records->map(fn ($row) => array_values((array) $row))->all();
        if ($type === 'clientes_mes' || $type === 'ventas_mes') {
            $rows = array_map(fn ($row) => [$row[0], (string) $row[1], ...($type === 'clientes_mes' ? [] : ['S/ '.number_format((float) $row[2], 2, '.', ',')])], $rows);
        }

        return ['module' => $module, 'title' => $title, 'dateField' => $dateField, 'columns' => $columns, 'rows' => $rows, 'pagination' => $pagination, 'total' => $pagination ? $pagination->total() : count($rows)];
    }

    private function paymentsQuery(string $type, string $from, string $to)
    {
        $query = DB::table('pagos')->join('contratos', 'contratos.contrato_id', '=', 'pagos.contrato_id')->join('clientes', 'clientes.cliente_id', '=', 'contratos.cliente_id')
            ->whereBetween('pagos.fecha_vencimiento', [$from, $to]);
        $type === 'pagos_pendientes' ? $query->where('pagos.estado', 'PENDIENTE') : $query->whereIn('pagos.estado', ['PENDIENTE','VENCIDO','IMPAGO'])->whereDate('pagos.fecha_vencimiento','<',now()->toDateString());

        return $query->select('pagos.codigo', 'clientes.nombres_razon_social AS cliente', 'contratos.codigo AS contrato', 'pagos.fecha_vencimiento', DB::raw('GREATEST(0, DATEDIFF(CURRENT_DATE(), pagos.fecha_vencimiento)) AS dias_atraso'), 'pagos.monto', 'pagos.estado')->orderBy('pagos.fecha_vencimiento');
    }

    public function generate(array $data): array
    {
        $report = $this->report($data['type'], $data['from'], $data['to']);
        $generated = now('America/Lima');
        $report['parameters'] = [
            ['Empresa', 'Lunnys Perú R.2.D.'], ['Reporte', $report['title']], ['Origen', 'Base de datos operativa'],
            ['Desde', $data['from']], ['Hasta', $data['to']], ['Campo o corte', $report['dateField']],
            ['Generado', $generated->format('d/m/Y H:i:s').' (America/Lima)'], ['Total de registros', (string) count($report['rows'])],
        ];
        $pdf = $data['format'] === 'pdf';

        return [
            'content' => $pdf ? (new ReportPdf)->render($report) : (new ReportWorkbook)->render($report),
            'mime' => $pdf ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'name' => 'lunnys_'.$data['type'].'_'.$generated->format('Ymd_His').'.'.$data['format'],
        ];
    }
}
