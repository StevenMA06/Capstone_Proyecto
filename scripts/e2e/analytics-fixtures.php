<?php

// Solo desde fixture.php, después de verificar entorno y base E2E.
use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

return function (string $command, string $arg, User $actor, array $clientData, array $planData): array {
    if ($command === 'ri-oracle') {
        [$from, $to] = explode('|', $arg);
        $clients = DB::table('clientes')->whereBetween('creado_en', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('cliente_id')->get(['cliente_id', 'numero_documento', 'creado_en']);
        $durations = DB::table('ejecuciones_instalacion')->whereBetween('fin_en', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotNull('inicio_en')->whereRaw('fin_en > inicio_en')->selectRaw('TIMESTAMPDIFF(SECOND, inicio_en, fin_en) as seconds')->pluck('seconds')->map(fn($v)=>(int)$v)->all();
        return ['clients' => $clients->toArray(), 'count' => $clients->count(), 'seconds' => $durations,
            'active_contracts' => DB::table('contratos')->where('estado', 'ACTIVO')->count(),
            'overdue_payments' => DB::table('pagos')->where('estado', 'VENCIDO')->count(),
            'critical_materials' => DB::table('materiales')->where('estado', 'ACTIVO')->whereColumn('stock_actual', '<=', 'stock_minimo')->get(['codigo', 'nombre', 'stock_actual'])->toArray()];
    }
    if (!in_array($command, ['ri-data', 'ri-ai-data'], true)) throw new RuntimeException('Operación analítica E2E desconocida.');
    do {
        $start = CarbonImmutable::parse('2000-01-01')->addDays(random_int(0, 5000));
        $end = $start->addDays(2);
    } while (DB::table('clientes')->whereBetween('creado_en', [$start->subDay(), $end->addDays(2)])->exists()
        || DB::table('ejecuciones_instalacion')->whereBetween('fin_en', [$start->subDay(), $end->addDays(2)])->exists());
    $dates = [$start->subSecond(), $start, $start->addDay()->setTime(12, 0), $end->endOfDay()->startOfSecond(), $end->addDay()];
    $clients = [];
    foreach ($dates as $date) {
        do { $clientData['numero_documento'] = (string) random_int(80000000, 89999999); } while (Client::where('numero_documento', $clientData['numero_documento'])->exists());
        $client = Client::create($clientData + ['estado' => 'ACTIVO', 'creado_por' => $actor->getKey()]);
        DB::table('clientes')->where('cliente_id', $client->getKey())->update(['creado_en' => $date->format('Y-m-d H:i:s')]);
        $clients[] = ['id' => $client->getKey(), 'document' => $client->numero_documento, 'date' => $date->format('Y-m-d H:i:s')];
    }
    // Marcas sintéticas para comprobar la fórmula, no certifican RF-INS ni Drive.
    $technician = Employee::create(['tipo_documento' => 'OTRO', 'numero_documento' => bin2hex(random_bytes(6)),
        'nombres' => 'Técnico', 'apellidos' => 'Medición ficticia', 'cargo' => 'Técnico de Campo', 'estado' => 'ACTIVO',
        'correo' => 'medicion.'.bin2hex(random_bytes(6)).'@example.test', 'telefono' => '900111222']);
    foreach ([10, 30] as $i => $minutes) {
        $clientData['numero_documento'] = (string) random_int(80000000, 89999999);
        $state = (require __DIR__.'/commercial-fixtures.php')('commercial-contract', '', $actor, $clientData, $planData);
        $begin = $start->addDays($i)->setTime(9, 0);
        DB::table('pedidos_instalacion')->where('pedido_id', $state['order']->pedido_id)->update(['estado' => 'COMPLETADO']);
        DB::table('ejecuciones_instalacion')->insert(['pedido_id' => $state['order']->pedido_id,
            'tecnico_id' => $technician->getKey(), 'inicio_en' => $begin, 'fin_en' => $begin->addMinutes($minutes), 'estado' => 'COMPLETADA']);
    }
    $tag = bin2hex(random_bytes(6));
    $role = Role::create(['nombre' => 'QA Reportes '.$tag, 'descripcion' => 'Alcance limitado ficticio', 'estado' => 'ACTIVO']);
    DB::table('rol_permiso')->where('rol_id', $role->getKey())->delete();
    foreach (['office.view', 'reports.view', 'clients.view'] as $code) {
        DB::table('rol_permiso')->insert(['rol_id' => $role->getKey(), 'permiso_id' => DB::table('permisos')->where('codigo', $code)->value('permiso_id')]);
    }
    $limited = User::create(['nombre_usuario' => 'qa_report_'.$tag, 'correo' => 'report.'.$tag.'@example.test',
        'password_hash' => 'PruebasLunnys2026', 'estado' => 'ACTIVO', 'rol_id' => $role->getKey()]);
    $limited->roles()->sync([$role->getKey()]);
    if ($command === 'ri-ai-data') {
        // Datos fuente ficticios; no acreditan activación contractual ni registro de pagos.
        $clientData['numero_documento'] = (string) random_int(80000000, 89999999);
        (require __DIR__.'/commercial-fixtures.php')('commercial-payment-data', '', $actor, $clientData, $planData);
        $material = (require __DIR__.'/inventory-fixtures.php')('inventory-material', '', $actor, $clientData, $planData);
        DB::table('materiales')->where('material_id', $material['material_id'])->update(['stock_actual' => '1']);
    }
    return ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'clients' => $clients,
        'inside' => array_slice($clients, 1, 3), 'limited' => $limited->nombre_usuario];
};
