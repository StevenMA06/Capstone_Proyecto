<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use App\Services\AutomaticIndicatorService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Herramienta CLI exclusiva de Playwright: no añade rutas ni cambia producción.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    $database = getenv('DB_DATABASE') ?: '';
    if (getenv('APP_ENV') !== 'testing' || ! preg_match('/^[a-zA-Z0-9_]+_e2e_test$/D', $database)) {
        fwrite(STDERR, "Playwright solo permite APP_ENV=testing y base terminada en _e2e_test.\n");
        exit(1);
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config('app.env') !== 'testing' || config('database.connections.mysql.database') !== $database) {
        throw new RuntimeException('La configuración efectiva no corresponde a la base de pruebas.');
    }

    $command = $argv[1] ?? '';
    if ($command === 'init') {
        $pdo = new PDO('mysql:host='.config('database.connections.mysql.host').';port='.config('database.connections.mysql.port'), config('database.connections.mysql.username'), config('database.connections.mysql.password'));
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (Artisan::call('migrate', ['--force' => true]) !== 0) throw new RuntimeException(Artisan::output());
        $role = Role::where('nombre', Role::MANAGER)->firstOrFail();
        $actor = User::updateOrCreate(['nombre_usuario' => 'qa_e2e_gerencia'], [
            'rol_id' => $role->getKey(), 'correo' => 'qa.e2e@example.test',
            'password_hash' => 'PruebasLunnys2026', 'estado' => 'ACTIVO', 'empleado_id' => null,
        ]);
        $actor->roles()->sync([$role->getKey()]);
        $employee = Employee::firstOrCreate(['tipo_documento' => 'OTRO', 'numero_documento' => 'QA-E2E-VENTAS'], [
            'nombres' => 'Asistente', 'apellidos' => 'Pruebas', 'cargo' => 'Asistente de Ventas',
            'correo' => 'asistente.e2e@example.test', 'telefono' => '900111222', 'estado' => 'ACTIVO',
        ]);
        $zone = DB::table('zonas_cobertura')->where('codigo', 'QA-E2E')->first();
        if (! $zone) $zone = (object) ['zona_id' => DB::table('zonas_cobertura')->insertGetId([
            'codigo' => 'QA-E2E', 'nombre' => 'Zona de pruebas', 'distrito' => 'Trujillo',
            'departamento' => 'La Libertad', 'estado' => 'ACTIVA', 'creado_por' => $actor->getKey(),
        ])];
        $output = ['laravel' => app()->version(), 'php' => PHP_VERSION, 'database' => $database,
            'database_version' => DB::selectOne('SELECT VERSION() AS version')->version,
            'employee_id' => $employee->getKey(), 'zone_id' => $zone->zona_id];
    } else {
        $actor = User::where('nombre_usuario', 'qa_e2e_gerencia')->firstOrFail();
        $tag = bin2hex(random_bytes(6));
        $clientData = [
            'tipo_documento' => 'DNI', 'nombres_razon_social' => 'Ana Lucía', 'apellidos' => 'Torres Vega',
            'correo' => "ana.{$tag}@example.test", 'telefono' => '900111222', 'direccion' => 'Av. Pruebas 100',
            'distrito' => 'Trujillo', 'ip_router' => '192.168.10.1', 'modelo_router' => 'Router QA',
            'usuario_router' => 'qa', 'contrasena_router' => 'RouterPruebas2026', 'ssid' => 'Lunnys-QA',
            'contrasena_wifi' => 'WifiPruebas2026',
        ];
        do { $clientData['numero_documento'] = (string) random_int(80000000, 89999999); }
        while (Client::where('numero_documento', $clientData['numero_documento'])->exists());
        $planData = ['nombre' => "QA Fibra {$tag}", 'tipo_servicio' => 'Internet residencial',
            'velocidad_mbps' => 100, 'precio_mensual' => '79.90', 'descripcion' => 'Plan ficticio E2E',
            'condiciones' => 'Instalación sujeta a cobertura'];
        if (str_starts_with($command, 'ri-')) {
            $output = (require __DIR__.'/analytics-fixtures.php')($command, $argv[2] ?? '', $actor, $clientData, $planData);
        } elseif (str_starts_with($command, 'pi-')) {
            $output = (require __DIR__.'/installation-fixtures.php')($command, $argv[2] ?? '', $actor, $clientData, $planData);
        } elseif (str_starts_with($command, 'kz-')) {
            $output = (require __DIR__.'/kardex-zone-fixtures.php')($command, $argv[2] ?? '', $actor, $clientData, $planData);
        } elseif (str_starts_with($command, 'inventory-')) {
            $output = (require __DIR__.'/inventory-fixtures.php')($command, $argv[2] ?? '', $actor, $clientData, $planData);
        } elseif (str_starts_with($command, 'commercial-')) {
            $output = (require __DIR__.'/commercial-fixtures.php')($command, $argv[2] ?? '', $actor, $clientData, $planData);
        } elseif ($command === 'indicator-order-state') {
            if (! ctype_digit((string) ($argv[2] ?? ''))) throw new RuntimeException('Pedido de indicador E2E inválido.');
            $output = ['rows' => DB::table('mediciones_automaticas')->where('pedido_id', (int) $argv[2])
                ->orderBy('medicion_id')->get()->map(fn ($row) => (array) $row)->all()];
        } elseif ($command === 'indicator-state') {
            [$indicator, $referenceType, $referenceId] = array_pad(explode('|', $argv[2] ?? '', 3), 3, null);
            if (! in_array($indicator, ['clientes', 'contratos', 'pagos', 'equipos', 'materiales', 'pedido_recursos', 'pedido_asignacion'], true)
                || ! ctype_digit((string) $referenceId)) throw new RuntimeException('Consulta de indicador E2E inválida.');
            $output = ['rows' => DB::table('mediciones_automaticas')->where('indicador', $indicator)
                ->where('referencia_tipo', $referenceType)->where('referencia_id', (int) $referenceId)
                ->orderBy('medicion_id')->get()->map(fn ($row) => (array) $row)->all()];
        } elseif ($command === 'indicator-deterministic') {
            // Casos controlados contra MySQL E2E. Carbon se restablece antes de salir
            // para no afectar los demás fixtures ni el servidor HTTP.
            $end = CarbonImmutable::create(2026, 10, 22, 10, 0, 0, 'America/Lima');
            Carbon::setTestNow($end);
            try {
                $service = app(AutomaticIndicatorService::class);
                $baseReference = random_int(900000000, 999999000);
                $tokens = [];
                foreach ([[10, 10, 0], [60, 60, 0], [150, 120, 30], [180, 150, 30]] as $index => [$elapsed, $active, $discarded]) {
                    $token = (string) Str::uuid();
                    $tokens[] = $token;
                    $start = $end->subSeconds($elapsed);
                    $request = Request::create('/', 'POST', [
                        'indicador_token' => $token,
                        'indicador_inicio' => $start->setTimezone('UTC')->format('Y-m-d\\TH:i:s.v\\Z'),
                        'indicador_ultima_interaccion' => $end->setTimezone('UTC')->format('Y-m-d\\TH:i:s.v\\Z'),
                        'indicador_activo_segundos' => $active,
                        'indicador_pausa_descartada_segundos' => $discarded,
                    ]);
                    $service->complete($request, $actor, 'clientes', 'prueba_indicador', $baseReference + $index);
                }
                $output = ['rows' => DB::table('mediciones_automaticas')->whereIn('token', $tokens)
                    ->orderBy('medicion_id')->get()->map(fn ($row) => (array) $row)->all()];
            } finally {
                Carbon::setTestNow();
            }
        } elseif ($command === 'indicator-order-summary') {
            $date = $argv[2] ?? now('America/Lima')->toDateString();
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) throw new RuntimeException('Fecha de indicador inválida.');
            $rows = DB::table('mediciones_automaticas as m')->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'm.pedido_id')
                ->whereIn('m.indicador', ['pedido_recursos', 'pedido_asignacion'])->where('m.estado', 'COMPLETADA')
                ->whereNotNull('p.asignado_en')->whereBetween('p.asignado_en', [$date.' 00:00:00', $date.' 23:59:59'])
                ->groupBy('m.pedido_id')
                ->havingRaw("SUM(CASE WHEN m.indicador = 'pedido_recursos' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN m.indicador = 'pedido_asignacion' THEN 1 ELSE 0 END) > 0")
                ->select('m.pedido_id')->selectRaw('SUM(m.duracion_activa_segundos) AS duracion')->get();
            $panel = app(AutomaticIndicatorService::class)->orderProgramming($date, $date);
            $output = ['raw' => ['count' => $rows->count(), 'average' => $rows->isEmpty() ? null : (float) $rows->avg('duracion') / 60], 'panel' => $panel];
        } else {
        $output = match ($command) {
            'client-data' => $clientData,
            'client' => Client::create($clientData + ['creado_por' => $actor->getKey(), 'estado' => 'PENDIENTE'])->only(['cliente_id', 'numero_documento', 'correo', 'telefono', 'nombres_razon_social', 'apellidos', 'ip_router']),
            'plan-data' => $planData,
            'plan' => app(App\Services\PlanService::class)->create($planData, $actor)->only(['plan_id', 'numero_plan', 'nombre', 'velocidad_mbps', 'precio_mensual', 'estado']),
            'client-state' => ['count' => Client::where('numero_documento', $argv[2] ?? '')->count(), 'record' => Client::where('numero_documento', $argv[2] ?? '')->first()?->only(['cliente_id', 'nombres_razon_social', 'apellidos', 'correo', 'telefono', 'ip_router', 'estado'])],
            'plan-state' => ['count' => ServicePlan::where('nombre', $argv[2] ?? '')->count(), 'record' => ServicePlan::where('nombre', $argv[2] ?? '')->first()?->only(['plan_id', 'numero_plan', 'nombre', 'velocidad_mbps', 'precio_mensual', 'estado'])],
            'contract-count' => ['count' => DB::table('contratos')->count()],
            'contract-data' => ['cliente_id' => Client::create($clientData + ['creado_por' => $actor->getKey(), 'estado' => 'PENDIENTE'])->getKey(), 'zona_id' => DB::table('zonas_cobertura')->where('codigo', 'QA-E2E')->value('zona_id'), 'responsable_id' => Employee::where('numero_documento', 'QA-E2E-VENTAS')->value('empleado_id'), 'fecha_inicio' => now('America/Lima')->addDay()->toDateString(), 'duracion_meses' => 12],
            default => throw new RuntimeException('Operación E2E desconocida.'),
        };
        }
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    // No permitir que el manejador de consola de Laravel devuelva texto con salida 0.
    fwrite(STDERR, "Falló la preparación E2E: ".$error->getMessage()."\n");
    if (str_contains($error->getMessage(), '[1045]')) {
        fwrite(STDERR, "MySQL rechazó las credenciales. Revisa E2E_DB_USERNAME y E2E_DB_PASSWORD en .env.e2e.\n");
    }
    exit(1);
}
