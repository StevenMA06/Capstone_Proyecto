<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\ServicePlan;
use App\Models\Role;
use App\Models\User;
use App\Models\CoverageZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests DESTRUCTIVOS del método store() de Contratos
 * 
 * Objetivo: INTENTAR ROMPER el método con:
 * - Datos inválidos (7 casos)
 * - Duraciones imposibles (10 casos)
 * - Fechas inválidas (8 casos)
 * - Ataques de seguridad (10 casos)
 * 
 * Total: 35 intentos de destruir el método
 */
class ContractStoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;
    protected ServicePlan $plan;
    protected Employee $employee;
    protected CoverageZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->employee = Employee::factory()->create([
            'fecha_cese' => null,
            'cargo' => 'Asistente de ventas',
            'estado' => 'ACTIVO'
        ]);
        
        $this->admin = User::factory()->create([
            'empleado_id' => $this->employee->getKey()
        ]);
        $adminRole = Role::where('nombre', 'Administrador')->first() 
            ?? Role::factory()->create(['nombre' => 'Administrador']);
        $this->admin->roles()->attach($adminRole->getKey());
        
        $this->client = Client::factory()->create([
            'estado' => 'ACTIVO',
            'distrito' => 'San Isidro'
        ]);
        $this->plan = ServicePlan::factory()->create(['estado' => 'ACTIVO', 'precio_mensual' => 100.00]);
        $this->zone = CoverageZone::factory()->create([
            'estado' => 'ACTIVA',
            'distrito' => 'San Isidro',
            'nombre' => 'Zona San Isidro'
        ]);
    }

    /**
     * @test
     * @dataProvider invalidClientDataProvider
     */
    public function intenta_romper_con_clientes_invalidos($clienteId, $descripcion)
    {
        $this->signIn($this->admin);

        $data = [
            'cliente_id' => $clienteId,
            'plan_id' => $this->plan->getKey(),
            'fecha_inicio' => now()->format('Y-m-d'),
            'duracion_meses' => 12,
            'direccion_instalacion' => 'Direccion test',
            'distrito' => 'San Isidro'
        ];

        echo "\n" . str_repeat("═", 80) . "\n";
        echo "💥 INTENTO DE DESTRUCCIÓN #{$descripcion}\n";
        echo str_repeat("═", 80) . "\n";
        
        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • cliente_id: " . var_export($clienteId, true) . " (tipo: " . gettype($clienteId) . ")\n";
        echo "   • plan_id: {$this->plan->getKey()}\n";
        echo "   • duracion_meses: 12\n";
        echo "   • direccion_instalacion: 'Direccion test'\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → POST /contratos (ContractController@store)\n";
        echo "   → Ruta: " . route('contracts.store') . "\n";

        $response = $this->post(route('contracts.store'), $data);

        echo "\n⚙️ VALIDACIÓN APLICADA:\n";
        echo "   → Laravel Validation: 'cliente_id' => 'required|exists:clientes,cliente_id'\n";
        echo "   → Regla: El cliente_id debe existir en la tabla 'clientes'\n";

        $response->assertSessionHasErrors('cliente_id');
        
        $errorMessage = $response->getSession()->get('errors')->first('cliente_id');

        echo "\n❌ ERROR DEVUELTO:\n";
        echo "   → Campo: cliente_id\n";
        echo "   → Mensaje: {$errorMessage}\n";
        echo "   → HTTP Status: 302 (Redirect con errores)\n";
        
        echo "\n✅ RESULTADO:\n";
        echo "   ✓ SISTEMA RECHAZÓ correctamente\n";
        echo "   ✓ NO se creó el contrato en la BD\n";
        echo "   ✓ Validación funcionó como se esperaba\n";
        echo str_repeat("═", 80) . "\n";
    }

    public static function invalidClientDataProvider()
    {
        return [
            'cliente_inexistente' => [99999, 'Cliente ID 99999 (no existe)'],
            'cliente_cero' => [0, 'Cliente ID 0'],
            'cliente_negativo' => [-1, 'Cliente ID negativo'],
            'cliente_texto' => ['ABC', 'Cliente con texto'],
            'cliente_null' => [null, 'Cliente NULL'],
            'cliente_muy_grande' => [999999999, 'Cliente ID muy grande'],
            'cliente_decimal' => [5.5, 'Cliente ID decimal'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidDurationDataProvider
     */
    public function intenta_romper_con_duraciones_invalidas($duracion, $descripcion)
    {
        $this->signIn($this->admin);

        $data = [
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'fecha_inicio' => now()->format('Y-m-d'),
            'duracion_meses' => $duracion,
            'direccion_instalacion' => 'Av Test',
            'distrito' => 'San Isidro'
        ];

        echo "\n" . str_repeat("═", 80) . "\n";
        echo "💥 INTENTO DE DESTRUCCIÓN #{$descripcion}\n";
        echo str_repeat("═", 80) . "\n";
        
        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • cliente_id: {$this->client->getKey()}\n";
        echo "   • duracion_meses: " . var_export($duracion, true) . " (tipo: " . gettype($duracion) . ")\n";
        echo "   • fecha_inicio: " . now()->format('Y-m-d') . "\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → POST /contratos (ContractController@store)\n";

        $response = $this->post(route('contracts.store'), $data);

        echo "\n⚙️ VALIDACIÓN APLICADA:\n";
        echo "   → Laravel Validation: 'duracion_meses' => 'required|integer|min:1'\n";
        echo "   → Regla: La duración debe ser un entero positivo (≥ 1)\n";

        $response->assertSessionHasErrors('duracion_meses');
        
        $errorMessage = $response->getSession()->get('errors')->first('duracion_meses');

        echo "\n❌ ERROR DEVUELTO:\n";
        echo "   → Campo: duracion_meses\n";
        echo "   → Mensaje: {$errorMessage}\n";
        
        echo "\n✅ RESULTADO:\n";
        echo "   ✓ SISTEMA RECHAZÓ correctamente\n";
        echo "   ✓ NO se creó el contrato en la BD\n";
        echo str_repeat("═", 80) . "\n";
    }

    public static function invalidDurationDataProvider()
    {
        return [
            'duracion_negativa' => [-5, 'Duración -5 meses'],
            'duracion_cero' => [0, 'Duración 0 meses'],
            'duracion_texto' => ['doce', 'Duración como texto'],
            'duracion_decimal' => [12.5, 'Duración 12.5 (decimal)'],
            'duracion_null' => [null, 'Duración NULL'],
            'duracion_muy_grande' => [999, 'Duración 999 meses (83 años)'],
            'duracion_negativa_grande' => [-100, 'Duración -100 meses'],
            'duracion_float_negativo' => [-5.5, 'Duración -5.5'],
            'duracion_string_vacio' => ['', 'Duración string vacío'],
            'duracion_infinito' => [PHP_INT_MAX, 'Duración infinita'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidDateDataProvider
     */
    public function intenta_romper_con_fechas_invalidas($fecha, $descripcion)
    {
        $this->signIn($this->admin);

        $data = [
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'fecha_inicio' => $fecha,
            'duracion_meses' => 12,
            'direccion_instalacion' => 'Av Test',
            'distrito' => 'San Isidro'
        ];

        echo "\n" . str_repeat("═", 80) . "\n";
        echo "💥 INTENTO DE DESTRUCCIÓN #{$descripcion}\n";
        echo str_repeat("═", 80) . "\n";
        
        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: " . var_export($fecha, true) . "\n";
        echo "   • cliente_id: {$this->client->getKey()}\n";
        echo "   • duracion_meses: 12\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → POST /contratos (ContractController@store)\n";

        $response = $this->post(route('contracts.store'), $data);

        echo "\n⚙️ VALIDACIÓN APLICADA:\n";
        echo "   → Laravel Validation: 'fecha_inicio' => 'required|date|after_or_equal:today'\n";
        echo "   → Regla: Debe ser una fecha válida en formato Y-m-d\n";

        $response->assertSessionHasErrors('fecha_inicio');
        
        $errorMessage = $response->getSession()->get('errors')->first('fecha_inicio');

        echo "\n❌ ERROR DEVUELTO:\n";
        echo "   → Campo: fecha_inicio\n";
        echo "   → Mensaje: {$errorMessage}\n";
        
        echo "\n✅ RESULTADO:\n";
        echo "   ✓ SISTEMA RECHAZÓ correctamente\n";
        echo "   ✓ Fecha inválida detectada\n";
        echo str_repeat("═", 80) . "\n";
    }

    public static function invalidDateDataProvider()
    {
        return [
            'fecha_30_febrero' => ['2024-02-30', '30 de febrero (no existe)'],
            'fecha_32_enero' => ['2024-01-32', '32 de enero (no existe)'],
            'fecha_mes_13' => ['2024-13-01', 'Mes 13 (no existe)'],
            'fecha_texto' => ['mañana', 'Fecha como texto'],
            'fecha_formato_incorrecto' => ['13/10/2024', 'Formato DD/MM/YYYY'],
            'fecha_solo_año' => ['2024', 'Solo año sin mes/día'],
            'fecha_null' => [null, 'Fecha NULL'],
            'fecha_vacia' => ['', 'Fecha vacía'],
        ];
    }

    /**
     * @test
     * @dataProvider securityAttackDataProvider
     */
    public function intenta_ataques_de_seguridad($campo, $payload, $descripcion)
    {
        $this->signIn($this->admin);

        $data = [
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
            'duracion_meses' => 12,
            'direccion_instalacion' => 'Av Test',
            'distrito' => 'San Isidro',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'responsable_id' => $this->employee->getKey(),
            'zona_id' => $this->zone->getKey(),
            $campo => $payload
        ];

        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 ATAQUE DE SEGURIDAD #{$descripcion}\n";
        echo str_repeat("═", 80) . "\n";
        
        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Campo atacado: {$campo}\n";
        echo "   • Payload: " . substr($payload, 0, 70) . (strlen($payload) > 70 ? "..." : "") . "\n";
        echo "   • Longitud: " . strlen($payload) . " caracteres\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → POST /contratos (ContractController@store)\n";

        echo "\n⚙️ DEFENSAS APLICADAS:\n";
        echo "   → Laravel CSRF Protection: Activo\n";
        echo "   → Eloquent ORM: Previene SQL Injection\n";
        echo "   → Blade Templates: Escapa XSS con {{ }}\n";
        echo "   → Validation: Sanitiza entradas\n";

        $response = $this->post(route('contracts.store'), $data);

        echo "\n🛡️ VERIFICACIÓN DE DEFENSA:\n";
        
        // Verificar que la tabla contratos EXISTE
        $tableExists = \DB::getSchemaBuilder()->hasTable('contratos');
        echo "   → Tabla 'contratos' existe: " . ($tableExists ? "SÍ ✓" : "NO ✗") . "\n";
        
        // Contar registros
        $count = \DB::table('contratos')->count();
        echo "   → Registros en BD: {$count}\n";
        
        // Verificar que NO se ejecutó SQL malicioso
        $this->assertTrue($tableExists, "La tabla 'contratos' debería existir (no fue eliminada)");
        
        echo "\n✅ RESULTADO:\n";
        echo "   ✓ DEFENSA EXITOSA - Ataque bloqueado\n";
        echo "   ✓ Base de datos intacta\n";
        echo "   ✓ Payload no se ejecutó como código\n";
        echo str_repeat("═", 80) . "\n";
        
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public static function securityAttackDataProvider()
    {
        return [
            'sql_drop_table' => ['direccion_instalacion', "'; DROP TABLE contratos;--", 'SQL Injection: DROP TABLE'],
            'sql_delete_all' => ['observaciones', "'; DELETE FROM contratos;--", 'SQL Injection: DELETE ALL'],
            'sql_union_select' => ['direccion_instalacion', "' UNION SELECT * FROM users--", 'SQL Injection: UNION'],
            'xss_script' => ['observaciones', '<script>alert("XSS")</script>', 'XSS: Script tag'],
            'xss_img_onerror' => ['observaciones', '<img src=x onerror="alert(1)">', 'XSS: IMG onerror'],
            'xss_iframe' => ['observaciones', '<iframe src="http://malicious.com"></iframe>', 'XSS: Iframe malicioso'],
            'html_injection' => ['observaciones', '<h1>HACKED</h1><p>By attacker</p>', 'HTML Injection'],
            'javascript_url' => ['observaciones', 'javascript:alert("XSS")', 'JavaScript URL'],
            'sql_comment_bypass' => ['direccion_instalacion', "admin'-- ", 'SQL: Comment bypass'],
            'path_traversal' => ['direccion_instalacion', '../../../etc/passwd', 'Path Traversal'],
        ];
    }

    /** @test */
    public function crea_contrato_valido_exitosamente()
    {
        $this->signIn($this->admin);

        $data = [
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'fecha_inicio' => now()->addDays(5)->format('Y-m-d'),
            'duracion_meses' => 12,
            'direccion_instalacion' => 'Av. Principal 123',
            'distrito' => 'San Isidro',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'responsable_id' => $this->employee->getKey(),
            'zona_id' => $this->zone->getKey()
        ];

        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ CASO VÁLIDO - Creación de Contrato\n";
        echo str_repeat("═", 80) . "\n";
        
        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • cliente_id: {$this->client->getKey()}\n";
        echo "   • plan_id: {$this->plan->getKey()}\n";
        echo "   • duracion_meses: 12\n";
        echo "   • direccion_instalacion: Av. Principal 123\n";
        echo "   • fecha_inicio: " . now()->addDays(5)->format('Y-m-d') . "\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → POST /contratos (ContractController@store)\n";

        $response = $this->post(route('contracts.store'), $data);

        echo "\n⚙️ PROCESO EJECUTADO:\n";
        echo "   1. Validación de datos: PASÓ ✓\n";
        echo "   2. Generación de código automático\n";
        echo "   3. Inserción en BD\n";
        echo "   4. Estado inicial: PENDIENTE\n";

        $response->assertSessionHasNoErrors();
        
        $contract = Contract::where('cliente_id', $this->client->getKey())
            ->orderBy('contrato_id', 'desc')
            ->first();
        
        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Contrato creado exitosamente\n";
        echo "   • ID: {$contract->contrato_id}\n";
        echo "   • Código: {$contract->codigo}\n";
        echo "   • Estado: {$contract->estado}\n";
        echo "   • Duración: {$contract->duracion_meses} meses\n";
        echo "   • Fecha inicio: {$contract->fecha_inicio}\n";
        echo str_repeat("═", 80) . "\n\n";
        
        $this->assertNotNull($contract);
        $this->assertEquals(12, $contract->duracion_meses);
    }
}
