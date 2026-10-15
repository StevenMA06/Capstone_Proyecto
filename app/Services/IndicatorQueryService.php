<?php
namespace App\Services;

class IndicatorQueryService
{
    public function measurement(string $key,string $from,string $to): array
    {
        $summary=app(PretestMeasurementService::class)->summary($from,$to,'all');
        $item=$summary['measurements'][$key];
        $status=!$item['validated'] ? 'pendiente_validacion' : (!$summary['enabled'] ? 'registro_no_habilitado' : ($item['count'] ? 'disponible' : 'sin_datos'));
        return ['success'=>true,'estado'=>$status,'indicador'=>$item['title'],'rf'=>$item['rf'],'fuente'=>'Observaciones manuales del pretest · levantamiento-pretest-v1',
            'periodo'=>['inicio'=>$from,'fin'=>$to,'campo'=>'fin','zona_horaria'=>'America/Lima'],
            'tiempo_promedio'=>['segundos'=>$item['average']===null?null:round($item['average']*60,2),'minutos'=>$item['average']===null?null:round($item['average'],2)],
            'tiempo_minimo_minutos'=>$item['minimum'],'tiempo_maximo_minutos'=>$item['maximum'],
            'total_registros_analizados'=>$item['count'],'casos_excepcionales'=>$item['exceptions'],
            'limites'=>['inicio'=>$item['start'],'fin'=>$item['end'],'evidencia'=>$item['evidence'],'alcance'=>$item['scope']],
            'atribucion'=>'El registrador de la observación no se interpreta como empleado ejecutor del proceso.',
        ];
    }

    public function inventoryByType(string $from,string $to): array
    {
        $base=$this->measurement('inventario',$from,$to);
        $base['por_tipo']=array_map(fn($type)=>['tipo'=>$type,'estado'=>'pendiente_validacion','tiempo_promedio_minutos'=>null,'total_registros_analizados'=>0],['Equipos','Materiales','Kardex']);
        return $base;
    }
}
