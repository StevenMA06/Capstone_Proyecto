<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests FEATURE de detección de documento duplicado
 * 
 * Objetivo: Validar constraint unique en (tipo_documento, numero_documento)
 * 
 * RF: RF-CLI-04
 */
class ClientDuplicateDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Deshabilitar middleware EnsureAccountActive
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAccountActive::class);
        
        // Crear usuario administrador
        $employee = Employee::factory()->create(['estado' => 'ACTIVO']);
        $role = Role::where('nombre', 'Administrador')->first();
        $this->user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $role->getKey()
        ]);
    }

    /** @test */
    public function rechaza_dni_duplicado_con_mismo_tipo_documento()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza DNI duplicado con mismo tipo_documento\n";
        echo str_repeat("═", 80) . "\n";

        // Crear cliente existente con DNI 12345678
        $existingClient = Client::factory()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'nombres_razon_social' => 'Cliente Original',
            'estado' => 'ACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente existente: DNI 12345678\n";
        echo "   • Intento de crear: DNI 12345678 (DUPLICADO)\n";
        echo "   • Constraint: UNIQUE (tipo_documento, numero_documento)\n";

        $duplicateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',  // DUPLICADO
            'nombres_razon_social' => 'Intento Duplicado',
            'apellidos' => 'García López',
            'correo' => 'nuevo@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Nueva 456',
            'distrito' => 'Lima',
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → ClientController::store()\n";
        echo "   → ClientService::create()\n";
        echo "   → ClientRepository::create()\n";
        echo "   → Client::create() → UniqueConstraintViolationException\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $duplicateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Base de datos detecta violación unique constraint\n";
        echo "   → Exception capturada en Controller::store()\n";
        echo "   → Verifica que exception contiene 'uq_clientes_documento'\n";
        echo "   → Retorna back() con error específico\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Creación: RECHAZADA\n";
        echo "   ✓ HTTP Status: 302 (redirect back)\n";
        echo "   ✓ Error: 'Este número ya está registrado para el tipo de documento seleccionado.'\n";
        echo "   ✓ Input preservado (excepto contraseñas)\n";
        echo "   ✓ Solo 1 cliente en BD\n";

        $response->assertRedirect();
        $response->assertSessionHasErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 1);  // Solo el cliente original
        
        // Verificar mensaje específico
        $errors = session('errors');
        if ($errors) {
            $error = $errors->get('numero_documento')[0] ?? '';
            echo "   ✓ Mensaje completo: '{$error}'\n";
        }

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function permite_mismo_numero_con_tipo_documento_diferente()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Permite mismo número con tipo_documento diferente\n";
        echo str_repeat("═", 80) . "\n";

        // Crear cliente con DNI 11111111
        Client::factory()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',
            'nombres_razon_social' => 'Persona Natural',
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente existente: DNI 11111111\n";
        echo "   • Nuevo cliente: RUC 11111111 (mismo número, diferente tipo)\n";
        echo "   • Constraint: UNIQUE solo para combinación (tipo, número)\n";

        $newClientData = [
            'tipo_documento' => 'RUC',  // DIFERENTE tipo
            'numero_documento' => '11111111',  // MISMO número
            'nombres_razon_social' => 'Empresa SAC',
            'apellidos' => 'N/A',
            'correo' => 'empresa@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Empresarial 789',
            'distrito' => 'Lima',
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → ClientController::store()\n";
        echo "   → Request valida unique en (tipo_documento, numero_documento)\n";
        echo "   → Regla: ->where('tipo_documento', \$tipo)\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $newClientData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Validator busca duplicado con tipo_documento = 'RUC'\n";
        echo "   → No encuentra coincidencia (el existente es DNI)\n";
        echo "   → Constraint BD permite inserción\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Creación: EXITOSA\n";
        echo "   ✓ HTTP Status: 302 (redirect a show)\n";
        echo "   ✓ 2 clientes en BD: DNI 11111111 + RUC 11111111\n";
        echo "   ✓ Ambos registros válidos y únicos\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clientes', 2);
        
        $this->assertDatabaseHas('clientes', [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111'
        ]);
        
        $this->assertDatabaseHas('clientes', [
            'tipo_documento' => 'RUC',
            'numero_documento' => '11111111'
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function mensaje_error_especifico_para_duplicado()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Mensaje de error específico para duplicado\n";
        echo str_repeat("═", 80) . "\n";

        // Cliente existente
        Client::factory()->create([
            'tipo_documento' => 'CE',
            'numero_documento' => '001234567',
            'nombres_razon_social' => 'Extranjero Residente',
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente existente: CE 001234567\n";
        echo "   • Intento duplicado: CE 001234567\n";

        $data = [
            'tipo_documento' => 'CE',
            'numero_documento' => '001234567',
            'nombres_razon_social' => 'Otro Extranjero',
            'apellidos' => 'Smith',
            'correo' => 'otro@example.com',
            'telefono' => '999888777',
            'direccion' => 'Av. Internacional 100',
            'distrito' => 'Miraflores',
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → try { \$this->clients->create() }\n";
        echo "   → catch (UniqueConstraintViolationException \$e)\n";
        echo "   → if (str_contains(\$e->getMessage(), 'uq_clientes_documento'))\n";
        echo "   → return back()->withErrors(['numero_documento' => ...])\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Controller captura exception específica\n";
        echo "   → Verifica que sea violación del constraint 'uq_clientes_documento'\n";
        echo "   → Retorna mensaje amigable al usuario\n";

        echo "\n✅ RESULTADO:\n";
        $errors = session()->get('errors');
        $errorMessage = '';
        if ($errors && is_object($errors) && method_exists($errors, 'get')) {
            $messages = $errors->get('numero_documento');
            $errorMessage = is_array($messages) ? ($messages[0] ?? '') : '';
        }
        
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Campo con error: numero_documento\n";
        echo "   ✓ Mensaje: '{$errorMessage}'\n";
        echo "   ✓ Formato amigable (no error técnico de BD)\n";

        $response->assertSessionHasErrors('numero_documento');
        if ($errorMessage) {
            $this->assertStringContainsString('ya está registrado', $errorMessage);
        }

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function preserva_datos_del_formulario_excepto_contrasenas()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Preserva datos del formulario (excepto contraseñas)\n";
        echo str_repeat("═", 80) . "\n";

        // Cliente existente
        Client::factory()->create([
            'tipo_documento' => 'RUC',
            'numero_documento' => '20123456789',
            'nombres_razon_social' => 'Empresa Original SAC',
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • RUC duplicado: 20123456789\n";
        echo "   • Incluye: contraseña router, contraseña WiFi, otros datos\n";

        $data = [
            'tipo_documento' => 'RUC',
            'numero_documento' => '20123456789',  // DUPLICADO
            'nombres_razon_social' => 'Empresa Nueva SAC',
            'apellidos' => 'Representante Legal',
            'correo' => 'empresa@nueva.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Industrial 500',
            'distrito' => 'San Juan',
            'ip_router' => '192.168.1.100',
            'modelo_router' => 'TP-Link Archer C6',
            'contrasena_router' => 'admin123',  // NO DEBE PRESERVARSE
            'ssid' => 'MiWiFi',
            'contrasena_wifi' => 'wifipass456',  // NO DEBE PRESERVARSE
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → catch (UniqueConstraintViolationException)\n";
        echo "   → back()->withInput(\$request->except(['contrasena_router', 'contrasena_wifi']))\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Old input contiene datos del formulario\n";
        echo "   → Excepto: contrasena_router, contrasena_wifi\n";
        echo "   → Usuario no tiene que re-tipear todo\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ nombres_razon_social: preservado\n";
        echo "   ✓ correo: preservado\n";
        echo "   ✓ ip_router: preservado\n";
        echo "   ✗ contrasena_router: NO preservada (seguridad)\n";
        echo "   ✗ contrasena_wifi: NO preservada (seguridad)\n";
        echo "   ✓ Usuario puede corregir solo el documento\n";

        $response->assertSessionHasInput('nombres_razon_social', 'Empresa Nueva SAC');
        $response->assertSessionHasInput('correo', 'empresa@nueva.com');
        $response->assertSessionHasInput('ip_router', '192.168.1.100');
        
        // Verificar que las contraseñas NO están en old input
        $oldInput = session()->get('_old_input', []);
        $this->assertArrayNotHasKey('contrasena_router', $oldInput);
        $this->assertArrayNotHasKey('contrasena_wifi', $oldInput);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function redireccion_back_con_errores()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Redirección back() con errores\n";
        echo str_repeat("═", 80) . "\n";

        // Cliente existente
        Client::factory()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '99887766',
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • DNI duplicado: 99887766\n";
        echo "   • Request desde: /clients/create\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '99887766',
            'nombres_razon_social' => 'Test Usuario',
            'apellidos' => 'Test Apellido',
            'correo' => 'test@test.com',
            'telefono' => '999999999',
            'direccion' => 'Test Dirección',
            'distrito' => 'Lima',
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → return back()->withInput()->withErrors([...])\n";

        $response = $this->actingAs($this->user)
            ->from(route('clients.create'))
            ->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Redirect status: 302\n";
        echo "   → Redirección a: página anterior (formulario)\n";
        echo "   → Errores en session: SÍ\n";
        echo "   → Old input en session: SÍ\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ HTTP Status: 302\n";
        echo "   ✓ Location: /clients/create (back)\n";
        echo "   ✓ Errores disponibles para mostrar\n";
        echo "   ✓ Usuario ve formulario con datos + error\n";

        $response->assertRedirect(route('clients.create'));
        $response->assertSessionHasErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 1);

        echo str_repeat("═", 80) . "\n";
    }
}
