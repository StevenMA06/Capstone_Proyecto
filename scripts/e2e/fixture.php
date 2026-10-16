<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
        $output = str_starts_with($command, 'commercial-')
            ? (require __DIR__.'/commercial-fixtures.php')($command, $argv[2] ?? '', $actor, $clientData, $planData)
            : match ($command) {
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
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    // No permitir que el manejador de consola de Laravel devuelva texto con salida 0.
    fwrite(STDERR, "Falló la preparación E2E: ".$error->getMessage()."\n");
    if (str_contains($error->getMessage(), '[1045]')) {
        fwrite(STDERR, "MySQL rechazó las credenciales. Revisa E2E_DB_USERNAME y E2E_DB_PASSWORD en .env.e2e.\n");
    }
    exit(1);
}
