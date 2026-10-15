<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

/** Explicit opt-in local sample. Never called by DatabaseSeeder or production migrations. */
class LocalAnalyticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $host=config('database.connections.'.config('database.default').'.host');
        if(!app()->environment('local','testing') || !in_array($host,['localhost','127.0.0.1','::1'],true)) {
            throw new \RuntimeException('Los datos PRUEBA-BI solo se cargan en una base local de desarrollo.');
        }
        if(DB::table('clientes')->where('numero_documento','like','PRUEBA-BI-C%')->exists()) {
            $this->command?->info('La muestra PRUEBA-BI ya está cargada; no se duplicó ni modificó.');
            return;
        }
        $creator=DB::table('usuarios')->orderBy('usuario_id')->value('usuario_id');
        if(!$creator) throw new \RuntimeException('Se necesita un usuario existente para la trazabilidad.');
        DB::transaction(function()use($creator): void {
            $employees=[];
            for($i=1;$i<=4;$i++) $employees[]=DB::table('empleados')->insertGetId([
                'tipo_documento'=>'OTRO','numero_documento'=>'PRUEBA-BI-E'.$i,'nombres'=>'Empleado de prueba',
                'apellidos'=>chr(64+$i),'cargo'=>$i===4?'Técnico de prueba':'Comercial de prueba','estado'=>'ACTIVO',
            ],'empleado_id');
            $zone=DB::table('zonas_cobertura')->insertGetId(['codigo'=>'PRUEBA-BI-ZONA','nombre'=>'Zona de prueba BI','distrito'=>'Distrito de prueba','estado'=>'ACTIVA','creado_por'=>$creator],'zona_id');
            $plans=[];
            foreach([89,119,149] as $i=>$price) {
                $plans[]=DB::table('planes_servicio')->insertGetId(['numero_plan'=>'PRUEBA-BI-PLAN'.($i+1),'nombre'=>'Plan de prueba '.($i+1),'tipo_servicio'=>'Internet','precio_mensual'=>$price,'velocidad_mbps'=>100*($i+1),'estado'=>'ACTIVO','creado_por'=>$creator],'plan_id');
                DB::table('plan_zona_cobertura')->insert(['plan_id'=>$plans[$i],'zona_id'=>$zone,'estado'=>'ACTIVO']);
            }
            $today=CarbonImmutable::now('America/Lima')->startOfDay(); $start=$today->subMonthsNoOverflow(5)->startOfMonth();
            $index=0;
            foreach([8,12,10,16,14,18] as $month=>$amount) {
                $monthStart=$start->addMonths($month); $lastDay=$monthStart->isSameMonth($today)?$today->day:$monthStart->daysInMonth;
                for($i=0;$i<$amount;$i++) {
                    $index++; $date=$monthStart->addDays(($i*3)%max(1,$lastDay))->setTime(9,0);
                    $client=DB::table('clientes')->insertGetId([
                        'tipo_documento'=>'OTRO','numero_documento'=>sprintf('PRUEBA-BI-C%03d',$index),
                        'nombres_razon_social'=>sprintf('Cliente de prueba BI %03d',$index),'direccion'=>'Dirección ficticia de prueba',
                        'distrito'=>['Zona Norte (prueba)','Zona Centro (prueba)','Zona Sur (prueba)'][$index%3],
                        'estado'=>$index%7===0?'INACTIVO':'ACTIVO','creado_por'=>$creator,'creado_en'=>$date->format('Y-m-d H:i:s'),
                    ],'cliente_id');
                    $state=['ACTIVO','ACTIVO','ACTIVO','PENDIENTE','CANCELADO'][$index%5]; $price=[89,119,149][$index%3];
                    $contract=DB::table('contratos')->insertGetId([
                        'codigo'=>sprintf('PRUEBA-BI-CON%03d',$index),'cliente_id'=>$client,'plan_id'=>$plans[$index%3],
                        'zona_id'=>$zone,'responsable_id'=>$employees[$index%3],'fecha_registro'=>$date->format('Y-m-d H:i:s'),
                        'fecha_inicio'=>$date->toDateString(),'duracion_meses'=>12,'fecha_fin'=>$date->addYear()->toDateString(),
                        'precio_contratado'=>$price,'direccion_servicio'=>'Dirección ficticia de prueba','estado'=>$state,
                        'observaciones'=>'PRUEBA-BI: datos ficticios para presentación, sin validez contractual.',
                    ],'contrato_id');
                    if($state!=='PENDIENTE') DB::table('ventas')->insert([
                        'contrato_id'=>$contract,'cliente_id'=>$client,'responsable_id'=>$employees[$index%3],
                        'fecha_venta'=>$date->format('Y-m-d H:i:s'),'monto'=>$price,'estado'=>$state==='CANCELADO'?'ANULADA':'CONFIRMADA',
                    ]);
                    $payment=['REALIZADO','REALIZADO','REALIZADO','PENDIENTE','VENCIDO','ANULADO'][$index%6];
                    $due=$payment==='VENCIDO'?$today->subDays(5+$index%20):$date->addMonth();
                    DB::table('pagos')->insert([
                        'codigo'=>sprintf('PRUEBA-BI-PAG%03d',$index),'contrato_id'=>$contract,'fecha_vencimiento'=>$due->toDateString(),
                        'fecha_pago'=>$payment==='REALIZADO'?$date->format('Y-m-d H:i:s'):null,'monto'=>$price,
                        'metodo_pago'=>$payment==='REALIZADO'?'Transferencia (prueba)':null,'referencia_externa'=>'PRUEBA-BI sin comprobante real',
                        'estado'=>$payment,'registrado_por'=>$creator,'creado_en'=>$date->format('Y-m-d H:i:s'),
                    ]);
                    $installation=['COMPLETADO','COMPLETADO','PROGRAMADO','PENDIENTE','EN_PROCESO','CANCELADO'][$index%6];
                    DB::table('pedidos_instalacion')->insert([
                        'codigo'=>sprintf('PRUEBA-BI-INS%03d',$index),'contrato_id'=>$contract,'cliente_id'=>$client,
                        'tecnico_id'=>$installation==='PENDIENTE'?null:$employees[3],'fecha_solicitud'=>$date->format('Y-m-d H:i:s'),
                        'fecha_programada'=>$installation==='PENDIENTE'?null:($installation==='PROGRAMADO'?$today->addDays(1+$index%5):$date)->format('Y-m-d H:i:s'),
                        'estado'=>$installation,'creado_por'=>$creator,'observaciones'=>'PRUEBA-BI: instalación ficticia; sin evidencias reales.',
                    ]);
                    if(Schema::hasTable('observaciones_pretest')) foreach(['clientes','contratos','instalaciones','pagos_externos'] as $key) {
                        $seconds=['clientes'=>120,'contratos'=>300,'instalaciones'=>420,'pagos_externos'=>180][$key]+($index*37)%240;
                        DB::table('observaciones_pretest')->insert([
                            'token'=>(string)Str::uuid(),'indicador'=>$key,'version_definicion'=>'levantamiento-pretest-v1',
                            'inicio'=>$date->format('Y-m-d H:i:s'),'fin'=>$date->addSeconds($seconds)->format('Y-m-d H:i:s'),
                            'duracion_segundos'=>$seconds,'interrupciones_segundos'=>0,'excepcional'=>$index%13===0,
                            'referencia'=>sprintf('PRUEBA-BI-%s-%03d',$key,$index),'observacion'=>'Medición ficticia para visualizar la interfaz. No es evidencia del pretest.',
                            'registrado_por'=>$creator,
                        ]);
                    }
                }
            }
            for($i=1;$i<=24;$i++) DB::table('equipos')->insert([
                'codigo'=>sprintf('PRUEBA-BI-EQP%02d',$i),'numero_serie'=>sprintf('PRUEBA-BI-SER%02d',$i),
                'nombre'=>'Router de prueba BI '.$i,'tipo'=>'Router','condicion'=>'BUENO','estado'=>['DISPONIBLE','DISPONIBLE','ASIGNADO','MANTENIMIENTO'][$i%4],
                'creado_por'=>$creator,'observaciones'=>'PRUEBA-BI ficticio','creado_en'=>$start->addDays($i*3)->format('Y-m-d H:i:s'),
            ]);
            foreach([['Cable de fibra','m',90,100],['Conectores','unidad',15,20],['Grapas','unidad',180,30],['Cable UTP','m',250,100]] as $i=>[$name,$unit,$stock,$minimum]) {
                $material=DB::table('materiales')->insertGetId([
                    'codigo'=>'PRUEBA-BI-MAT'.($i+1),'nombre'=>$name.' (prueba BI)','unidad_medida'=>$unit,
                    'stock_actual'=>$stock,'stock_minimo'=>$minimum,'estado'=>'ACTIVO','creado_por'=>$creator,'creado_en'=>$start->format('Y-m-d H:i:s'),
                ],'material_id');
                $balance=0;
                for($m=0;$m<6;$m++) {
                    $date=$start->addMonths($m)->addDays(2); if($date>$today) $date=$today;
                    $entry=50+10*$m; $exit=$m===5?$balance+$entry-$stock:20+5*$m;
                    DB::table('movimientos_inventario')->insert(['material_id'=>$material,'tipo_movimiento'=>'ENTRADA','cantidad'=>$entry,'cantidad_anterior'=>$balance,'cantidad_posterior'=>$balance+$entry,'responsable_id'=>$creator,'fecha_movimiento'=>$date->format('Y-m-d H:i:s'),'observacion'=>'PRUEBA-BI ficticio']);
                    $balance+=$entry;
                    DB::table('movimientos_inventario')->insert(['material_id'=>$material,'tipo_movimiento'=>'CONSUMO','cantidad'=>$exit,'cantidad_anterior'=>$balance,'cantidad_posterior'=>$balance-$exit,'responsable_id'=>$creator,'fecha_movimiento'=>$date->setTime(10,0)->format('Y-m-d H:i:s'),'observacion'=>'PRUEBA-BI ficticio']);
                    $balance-=$exit;
                }
            }
        });
        $this->command?->info('Muestra PRUEBA-BI cargada: 78 clientes/contratos/pagos/pedidos, personal, ventas, equipos, materiales, Kardex y tiempos ficticios.');
    }
}
