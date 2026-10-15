<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessIntelligenceService
{
    public const INDICATORS = [
        'clientes'=>['Clientes registrados','clientes','creado_en',null,null,'clientes'],
        'contratos'=>['Contratos registrados','contratos','fecha_registro','responsable_id',null,'contratos'],
        'ventas'=>['Ventas confirmadas de contratos','ventas','fecha_venta','responsable_id','monto','contratos'],
        'pagos'=>['Pagos','pagos','creado_en','registrado_por','monto','pagos'],
        'instalaciones'=>['Pedidos de instalación','pedidos_instalacion','fecha_solicitud','tecnico_id',null,'instalaciones'],
        'equipos'=>['Equipos registrados','equipos','creado_en',null,null,'inventario'],
        'materiales'=>['Materiales registrados','materiales','creado_en',null,null,'inventario'],
        'kardex'=>['Movimientos de Kardex','movimientos_inventario','fecha_movimiento','responsable_id',null,'inventario'],
    ];

    public function available(string $process): array
    {
        return array_filter(self::INDICATORS,fn($item)=>$process==='all' || $item[5]===$process);
    }

    private function source(string $indicator)
    {
        [, $table] = self::INDICATORS[$indicator];
        $query=DB::table($table.' as t');
        if ($indicator==='ventas') {
            // Each confirmed sale is uniquely linked to a contract; never treat all active contracts as new sales.
            $query->join('contratos as c','c.contrato_id','=','t.contrato_id')->where('t.estado','CONFIRMADA');
        }
        return $query;
    }

    public function chart(string $indicator,string $group,string $type,string $measure,string $from,string $to): array
    {
        if(!isset(self::INDICATORS[$indicator])) throw ValidationException::withMessages(['indicador'=>'Indicador no disponible.']);
        [$title,$table,$date,$employee,$amount] = self::INDICATORS[$indicator];
        if (!in_array($group,['dia','semana','mes','estado','empleado'],true) || !in_array($type,['line','bar','pie','doughnut','area'],true)) {
            throw ValidationException::withMessages(['agrupacion'=>'Selecciona una agrupación y gráfico disponibles.']);
        }
        if ($measure==='monto' && !in_array($indicator,['ventas','pagos'],true)) throw ValidationException::withMessages(['medida'=>'Este indicador solo admite cantidades.']);
        if (!in_array($measure,['cantidad','monto'],true)) throw ValidationException::withMessages(['medida'=>'Medida no disponible.']);
        $temporal=in_array($group,['dia','semana','mes'],true);
        if(!$temporal && in_array($type,['line','area'],true)) throw ValidationException::withMessages(['tipo_grafico'=>'Usa barras o circular para estados o empleados.']);
        if($group==='empleado' && !$employee) throw ValidationException::withMessages(['agrupacion'=>'No existe un responsable registrado para este indicador.']);
        if($group==='dia' && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to))>366) throw ValidationException::withMessages(['agrupacion'=>'Para más de un año utiliza semanas o meses.']);
        $query=$this->source($indicator)->whereBetween('t.'.$date,[$from.' 00:00:00',$to.' 23:59:59']);
        if($indicator==='pagos' && $measure==='monto') {
            $query=$this->source($indicator)->where('t.estado','REALIZADO')->whereBetween('t.fecha_pago',[$from.' 00:00:00',$to.' 23:59:59']);
            $date='fecha_pago';
        }
        if($group==='empleado') {
            if(in_array($indicator,['pagos','kardex'],true)) {
                $query->leftJoin('usuarios as u','u.usuario_id','=','t.'.$employee)->leftJoin('empleados as e','e.empleado_id','=','u.empleado_id');
            } else $query->leftJoin('empleados as e','e.empleado_id','=','t.'.$employee);
            $expression="COALESCE(CONCAT('EMP-',e.empleado_id,' · ',e.nombres,' ',e.apellidos),'Sin empleado asociado')";
        } else {
            $expression=match($group) {
                'dia'=>"DATE(t.$date)", 'semana'=>"DATE_SUB(DATE(t.$date), INTERVAL WEEKDAY(t.$date) DAY)",
                'mes'=>"DATE_FORMAT(t.$date,'%Y-%m')",
                'estado'=>$indicator==='kardex' ? 't.tipo_movimiento' : 't.estado',
            };
        }
        $value=$measure==='monto' ? "COALESCE(SUM(t.$amount),0)" : 'COUNT(*)';
        $rows=$query->selectRaw("$expression AS etiqueta, $value AS valor")->groupBy('etiqueta')->orderBy('etiqueta')->get();
        $values=$rows->pluck('valor','etiqueta')->map(fn($v)=>(float)$v)->all();
        if($temporal) {
            $series=[]; $start=CarbonImmutable::parse($from); $end=CarbonImmutable::parse($to);
            $cursor=match($group) {'mes'=>$start->startOfMonth(),'semana'=>$start->startOfWeek(),default=>$start};
            while($cursor<=$end) {
                $key=$cursor->format($group==='mes'?'Y-m':'Y-m-d');
                $series[$key]=$values[$key] ?? 0;
                $cursor=match($group) {'mes'=>$cursor->addMonth(),'semana'=>$cursor->addWeek(),default=>$cursor->addDay()};
            }
            $values=$series;
        }
        return ['indicator'=>$indicator,'title'=>$title,'group'=>$group,'type'=>$type,'measure'=>$measure,
            'unit'=>$measure==='monto'?'S/':'registros','labels'=>array_keys($values),'values'=>array_values($values),
            'total'=>array_sum($values),'dateField'=>$date,'from'=>$from,'to'=>$to,
            'scope'=>$indicator==='pagos' && $measure==='monto' ? 'Solo pagos realizados, por fecha de pago.' : 'Registros del período, por fecha de registro del proceso. Los estados son los vigentes.',
        ];
    }

    public function sales(string $from,string $to): array
    {
        $query=$this->source('ventas')->whereBetween('t.fecha_venta',[$from.' 00:00:00',$to.' 23:59:59']);
        return ['count'=>(clone $query)->count(),'amount'=>(float)$query->sum('t.monto')];
    }

    public function staff(string $from,string $to,int $top=10,?int $employee=null): array
    {
        $contracts=DB::table('contratos')->whereBetween('fecha_registro',[$from.' 00:00:00',$to.' 23:59:59'])
            ->select('responsable_id')->selectRaw('COUNT(*) AS contratos')->groupBy('responsable_id');
        $sales=$this->source('ventas')->whereBetween('t.fecha_venta',[$from.' 00:00:00',$to.' 23:59:59'])
            ->select('t.responsable_id')->selectRaw('COUNT(*) AS ventas, SUM(t.monto) AS monto')->groupBy('t.responsable_id');
        $query=DB::table('empleados as e')->leftJoinSub($contracts,'c','c.responsable_id','=','e.empleado_id')
            ->leftJoinSub($sales,'v','v.responsable_id','=','e.empleado_id')
            ->where(fn($q)=>$q->whereNotNull('c.responsable_id')->orWhereNotNull('v.responsable_id'));
        if($employee!==null) $query->where('e.empleado_id',$employee);
        $total=(clone $query)->count();
        $rows=$query->select('e.empleado_id','e.nombres','e.apellidos','e.cargo')
            ->selectRaw('COALESCE(c.contratos,0) AS contratos, COALESCE(v.ventas,0) AS ventas, COALESCE(v.monto,0) AS monto')
            ->orderByDesc('contratos')->orderByDesc('ventas')->orderBy('e.empleado_id')->limit($top)->get();
        return ['rows'=>$rows,'total'=>$total,'top'=>$top,'scope'=>'Contratos por fecha de registro y ventas confirmadas por fecha de venta. Se cuenta participación registrada; no se califica productividad ni éxito.'];
    }
}
