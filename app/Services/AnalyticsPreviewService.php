<?php
namespace App\Services;

use App\Support\IndicatorDefinitions;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Deterministic fictional records for visual review. No database access or persistence. */
class AnalyticsPreviewService
{
    private function records(): array
    {
        $end=CarbonImmutable::now('America/Lima')->startOfDay();
        $start=$end->subMonthsNoOverflow(6)->startOfMonth(); $data=[];
        $states=[
            'clientes'=>['ACTIVO','ACTIVO','ACTIVO','INACTIVO'],
            'contratos'=>['ACTIVO','ACTIVO','PENDIENTE','FINALIZADO','CANCELADO'],
            'pagos'=>['REALIZADO','REALIZADO','REALIZADO','PENDIENTE','VENCIDO','ANULADO'],
            'instalaciones'=>['COMPLETADO','COMPLETADO','PROGRAMADO','PENDIENTE','EN_PROCESO','CANCELADO'],
            'equipos'=>['DISPONIBLE','DISPONIBLE','ASIGNADO','MANTENIMIENTO'],
            'materiales'=>['ACTIVO','ACTIVO','ACTIVO','INACTIVO'],
            'kardex'=>['ENTRADA','SALIDA','DEVOLUCION','AJUSTE'],
        ];
        foreach($states as $key=>$options) {
            $rows=[]; $ordinal=0;
            for($day=$start;$day<=$end;$day=$day->addDay()) {
                $seed=(int)$day->format('z')+strlen($key)+(int)$day->format('n')*3;
                $count=($seed*7+strlen($key)*3)%5;
                for($i=0;$i<$count;$i++) {
                    $rows[]=['date'=>$day->toDateString(),'status'=>$options[$ordinal%count($options)],
                        'employee'=>$ordinal%4,'amount'=>[89,119,149,179][$ordinal%4]];
                    $ordinal++;
                }
            }
            $data[$key]=$rows;
        }
        $data['ventas']=array_map(fn($r)=>array_replace($r,['status'=>'CONFIRMADA']),array_values(array_filter($data['contratos'],fn($r)=>in_array($r['status'],['ACTIVO','FINALIZADO'],true))));
        return $data;
    }

    private function period(array $rows,string $from,string $to): array
    {
        return array_values(array_filter($rows,fn($r)=>$r['date'] >= $from && $r['date'] <= $to));
    }

    public function dashboard(string $from,string $to,string $process,array $options=[]): array
    {
        $records=$this->records(); $bi=new BusinessIntelligenceService;
        $start=CarbonImmutable::parse($from); $end=CarbonImmutable::parse($to); $days=(int)$start->diffInDays($end)+1;
        $previousFrom=$start->subDays($days)->toDateString(); $previousTo=$start->subDay()->toDateString();
        $items=[];
        $source=['clientes'=>['clientes','clientes_altas'],'contratos'=>['contratos','contratos_registrados'],'pagos'=>['pagos','pagos_recaudados'],'instalaciones'=>['instalaciones','instalaciones_estado'],'inventario'=>['kardex','kardex_movimientos']];
        foreach(AnalyticsService::PROCESSES as $key=>$label) {
            if($process!=='all' && $process!==$key) continue;
            [$indicator,$report]=$source[$key]; $rows=$records[$indicator];
            if($key==='pagos') $rows=array_values(array_filter($rows,fn($r)=>$r['status']==='REALIZADO'));
            $current=$this->period($rows,$from,$to); $before=count($this->period($rows,$previousFrom,$previousTo));
            $trend=[];
            for($month=$start->startOfMonth();$month<=$end;$month=$month->addMonth()) $trend[$month->format('Y-m')]=0;
            foreach($current as $row) $trend[substr($row['date'],0,7)]++;
            $states=[];
            foreach($records[$key==='inventario'?'equipos':$indicator] as $row) $states[$row['status']]=($states[$row['status']] ?? 0)+1;
            $items[$key]=['label'=>$label,'report'=>$report,'count'=>count($current),'before'=>$before,'trend'=>$trend,'states'=>$states];
        }
        $available=$bi->available($process); $indicator=$options['indicador'] ?? array_key_first($available);
        if(!isset($available[$indicator])) $indicator=array_key_first($available);
        $explorer=$this->chart($records,$indicator,$options['agrupacion'] ?? 'mes',$options['tipo_grafico'] ?? 'bar',$options['medida'] ?? 'cantidad',$from,$to);
        $salesRows=$this->period($records['ventas'],$from,$to);
        $sales=in_array($process,['all','contratos'],true)?['count'=>count($salesRows),'amount'=>(float)array_sum(array_column($salesRows,'amount'))]:null;
        $staffRows=[];
        foreach(range(0,3) as $employee) {
            $contracts=array_filter($this->period($records['contratos'],$from,$to),fn($r)=>$r['employee']===$employee);
            $employeeSales=array_filter($salesRows,fn($r)=>$r['employee']===$employee);
            if(!$contracts && !$employeeSales) continue;
            $staffRows[]=(object)['empleado_id'=>$employee+1,'nombres'=>'Empleado de prueba','apellidos'=>chr(65+$employee),'cargo'=>'Comercial','contratos'=>count($contracts),'ventas'=>count($employeeSales),'monto'=>array_sum(array_column($employeeSales,'amount'))];
        }
        usort($staffRows,fn($a,$b)=>($b->contratos<=>$a->contratos) ?: ($b->ventas<=>$a->ventas));
        $top=(int)($options['top'] ?? 10);
        $staff=$sales!==null?['rows'=>collect(array_slice($staffRows,0,$top)),'total'=>count($staffRows),'top'=>$top,'scope'=>'Participación ficticia para revisar la presentación.']:null;
        $materials=($process==='all'||$process==='inventario')?collect([
            (object)['nombre'=>'Cable de fibra (prueba)','stock_actual'=>350,'stock_minimo'=>100,'unidad_medida'=>'m'],
            (object)['nombre'=>'Conectores (prueba)','stock_actual'=>12,'stock_minimo'=>20,'unidad_medida'=>'unidad'],
            (object)['nombre'=>'Grapas (prueba)','stock_actual'=>80,'stock_minimo'=>30,'unidad_medida'=>'unidad'],
        ]):collect();
        $alerts=[];
        if($process==='all'||$process==='pagos') {
            $overdue=array_filter($records['pagos'],fn($r)=>$r['status']==='VENCIDO');
            $alerts[]=['label'=>'Pagos con vencimiento superado','value'=>count($overdue),'detail'=>'Saldo ficticio: S/ '.number_format(array_sum(array_column($overdue,'amount')),2),'module'=>'pagos','report'=>'pagos_vencidos'];
        }
        if($process==='all'||$process==='inventario') $alerts[]=['label'=>'Materiales que requieren reposición','value'=>1,'detail'=>'Ejemplo de alerta por stock mínimo.','module'=>'inventario','report'=>'materiales_bajo_minimo'];
        if($process==='all'||$process==='instalaciones') $alerts[]=['label'=>'Instalaciones pendientes de programación','value'=>count(array_filter($records['instalaciones'],fn($r)=>$r['status']==='PENDIENTE')),'detail'=>'Ejemplo de seguimiento de pedidos.','module'=>'instalaciones','report'=>'instalaciones_estado'];
        $measurements=[];
        foreach(IndicatorDefinitions::forProcess($process) as $key=>$definition) {
            $durations=[];
            if($definition['validated']) foreach($this->period($records[$definition['module']],$from,$to) as $i=>$row) $durations[]=round((120+($i*37+strlen($key)*31)%720)/60,2);
            $measurements[$key]=$definition+['count'=>count($durations),'average'=>$durations?array_sum($durations)/count($durations):null,'minimum'=>$durations?min($durations):null,'maximum'=>$durations?max($durations):null,'exceptions'=>0];
        }
        $pretest=['enabled'=>false,'measurements'=>$measurements,'recent'=>collect()];
        return compact('items','materials','alerts','previousFrom','previousTo','sales','staff','pretest','explorer')+['chartIndicators'=>$available];
    }

    private function chart(array $records,string $indicator,string $group,string $type,string $measure,string $from,string $to): array
    {
        [$title,,,,,$module]=BusinessIntelligenceService::INDICATORS[$indicator];
        if($measure==='monto'&&!in_array($indicator,['ventas','pagos'],true)) throw ValidationException::withMessages(['medida'=>'Este indicador solo admite cantidades.']);
        $temporal=in_array($group,['dia','semana','mes'],true);
        if(!$temporal&&in_array($type,['line','area'],true)) throw ValidationException::withMessages(['tipo_grafico'=>'Usa barras o circular para estados o empleados.']);
        if($group==='empleado'&&in_array($indicator,['clientes','equipos','materiales'],true)) throw ValidationException::withMessages(['agrupacion'=>'Este indicador no dispone de responsable.']);
        if($group==='dia'&&CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to))>366) throw ValidationException::withMessages(['agrupacion'=>'Para más de un año utiliza semanas o meses.']);
        $rows=$this->period($records[$indicator],$from,$to);
        if($indicator==='pagos'&&$measure==='monto') $rows=array_values(array_filter($rows,fn($r)=>$r['status']==='REALIZADO'));
        $values=[];
        if($temporal) {
            $cursor=CarbonImmutable::parse($from); $end=CarbonImmutable::parse($to);
            $cursor=match($group){'semana'=>$cursor->startOfWeek(),'mes'=>$cursor->startOfMonth(),default=>$cursor};
            while($cursor<=$end) {
                $values[$cursor->format($group==='mes'?'Y-m':'Y-m-d')]=0;
                $cursor=match($group){'semana'=>$cursor->addWeek(),'mes'=>$cursor->addMonth(),default=>$cursor->addDay()};
            }
        }
        foreach($rows as $row) {
            $date=CarbonImmutable::parse($row['date']);
            $label=match($group){'mes'=>$date->format('Y-m'),'semana'=>$date->startOfWeek()->toDateString(),'dia'=>$row['date'],'estado'=>$row['status'],'empleado'=>'Empleado de prueba '.chr(65+$row['employee'])};
            $values[$label]=($values[$label] ?? 0)+($measure==='monto'?$row['amount']:1);
        }
        return ['indicator'=>$indicator,'title'=>$title,'group'=>$group,'type'=>$type,'measure'=>$measure,'unit'=>$measure==='monto'?'S/':'registros',
            'labels'=>array_keys($values),'values'=>array_values($values),'total'=>array_sum($values),'dateField'=>'fecha ficticia','from'=>$from,'to'=>$to,'scope'=>'Datos ficticios para revisar el diseño; no representan operaciones de Lunnys.'];
    }
}
