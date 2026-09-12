<?php

namespace Tests\Support;

use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClientHistoryFixtures
{
    public static function attach(Client $client, User $creator, string $prefix, int $count = 4): void
    {
        $employee = Employee::create([
            'tipo_documento' => 'OTRO', 'numero_documento' => $prefix,
            'nombres' => 'Técnico', 'apellidos' => $prefix, 'cargo' => 'Instalador',
        ]);
        $plan = DB::table('planes_servicio')->insertGetId([
            'numero_plan' => $prefix, 'nombre' => 'Internet '.$prefix, 'tipo_servicio' => 'Internet',
            'precio_mensual' => 90, 'creado_por' => $creator->getKey(),
        ]);
        $zone = DB::table('zonas_cobertura')->insertGetId([
            'codigo' => $prefix, 'nombre' => 'Zona '.$prefix, 'distrito' => 'Trujillo',
            'creado_por' => $creator->getKey(),
        ]);
        for ($index = 0; $index < $count; $index++) {
            $contract = DB::table('contratos')->insertGetId([
                'codigo' => $prefix.'-C-'.$index, 'cliente_id' => $client->getKey(),
                'plan_id' => $plan, 'zona_id' => $zone, 'responsable_id' => $employee->getKey(),
                'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'duracion_meses' => 12,
                'precio_contratado' => 90, 'direccion_servicio' => 'Dirección original del contrato',
                'estado' => ['ACTIVO', 'FINALIZADO', 'CANCELADO', 'PENDIENTE'][$index % 4],
                'observaciones' => 'Contrato de prueba '.$prefix,
            ]);
            DB::table('pagos')->insert([
                'contrato_id' => $contract, 'codigo' => $prefix.'-P-'.$index,
                'fecha_vencimiento' => '2026-09-30', 'periodo_servicio' => '2026-09', 'monto' => 90,
                'fecha_pago' => $index % 4 === 0 ? '2026-09-01 10:00:00' : null,
                'metodo_pago' => $index % 4 === 0 ? 'Transferencia' : null,
                'estado' => ['REALIZADO', 'VENCIDO', 'ANULADO', 'PENDIENTE'][$index % 4],
                'registrado_por' => $creator->getKey(),
            ]);
            DB::table('pedidos_instalacion')->insert([
                'codigo' => $prefix.'-I-'.$index, 'contrato_id' => $contract,
                'cliente_id' => $client->getKey(), 'tecnico_id' => $index % 4 === 3 ? null : $employee->getKey(),
                'fecha_programada' => $index % 4 === 3 ? null : '2026-09-05 10:00:00',
                'estado' => ['COMPLETADO', 'CANCELADO', 'PROGRAMADO', 'PENDIENTE'][$index % 4],
                'creado_por' => $creator->getKey(), 'observaciones' => 'Instalación '.$prefix,
            ]);
        }
    }
}
