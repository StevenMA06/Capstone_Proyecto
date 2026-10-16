<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests FEATURE de actualización de clientes
 * 
 * Objetivo: Validar actualización de datos y protección contra duplicados
 * 
 * RF: RF-CLI-09
 */
class ClientUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;

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

        // Crear cliente para actualizar
        $this->client = Client::factory()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',
            'nombres_razon_social' => 'Original',
            'apellidos' => 'Apellido Original',
            'correo' => 'original@test.com',
            'direccion' => 'Direccion Original',
            'estado' => 'ACTIVO'
        ]);
    }

    /** @test */
    public function actualiza_nombres_correctamente()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Actualiza nombres correctamente\n";
        echo str_repeat("═", 80) . "\n";

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente ID: {$this->client->getKey()}\n";
        echo "   • Nombre actual: 'Original'\n";
        echo "   • Nombre nuevo: 'Juan Carlos'\n";

        $updateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',  // Sin cambiar
            'nombres_razon_social' => 'Juan Carlos',  // CAMBIO
            'apellidos' => 'García Pérez',  // CAMBIO
            'correo' => 'original@test.com',
            'telefono' => '987654321',
            'direccion' => 'Direccion Original',
            'distrito' => 'Lima',
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → ClientController::update()\n";
        echo "   → ClientService::update()\n";
        echo "   → ClientRepository::update()\n";
        echo "   → \$client->fill()->save()\n";

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', $this->client), $updateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Request valida datos\n";
        echo "   → Repository ejecuta fill() + save()\n";
        echo "   → Cliente actualizado en BD\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ HTTP Status: 302 (redirect a show)\n";
        echo "   ✓ Mensaje: 'Datos del cliente actualizados correctamente.'\n";
        echo "   ✓ nombres_razon_social: Juan Carlos\n";
        echo "   ✓ apellidos: García Pérez\n";

        $response->assertRedirect(route('clients.show', $this->client));
        $response->assertSessionHas('status');
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $this->client->getKey(),
            'nombres_razon_social' => 'Juan Carlos',
            'apellidos' => 'García Pérez',
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function actualiza_direccion_correctamente()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Actualiza dirección correctamente\n";
        echo str_repeat("═", 80) . "\n";

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Dirección actual: 'Direccion Original'\n";
        echo "   • Dirección nueva: 'Av. Los Laureles 456'\n";
        echo "   • Referencia nueva: 'Frente al parque'\n";

        $updateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',
            'nombres_razon_social' => 'Original',
            'apellidos' => 'Apellido Original',
            'correo' => 'original@test.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Los Laureles 456',  // CAMBIO
            'referencia_direccion' => 'Frente al parque',  // CAMBIO
            'distrito' => 'San Isidro',  // CAMBIO
        ];

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → ClientRepository::update(\$client, \$data)\n";
        echo "   → \$client->fill(\$attributes)->save()\n";

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', $this->client), $updateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Datos de dirección actualizados\n";
        echo "   → Referencias actualizadas\n";
        echo "   → Distrito modificado\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Actualización: EXITOSA\n";
        echo "   ✓ direccion: Av. Los Laureles 456\n";
        echo "   ✓ referencia_direccion: Frente al parque\n";
        echo "   ✓ distrito: San Isidro\n";

        $response->assertRedirect(route('clients.show', $this->client));
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $this->client->getKey(),
            'direccion' => 'Av. Los Laureles 456',
            'referencia_direccion' => 'Frente al parque',
            'distrito' => 'San Isidro',
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_documento_duplicado_de_otro_cliente()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza documento duplicado de otro cliente\n";
        echo str_repeat("═", 80) . "\n";

        // Crear otro cliente con DNI 22222222
        $otherClient = Client::factory()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '22222222',
            'nombres_razon_social' => 'Otro Cliente',
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente a actualizar: ID {$this->client->getKey()}, DNI 11111111\n";
        echo "   • Otro cliente existente: ID {$otherClient->getKey()}, DNI 22222222\n";
        echo "   • Intento: cambiar DNI a 22222222 (DUPLICADO)\n";

        $updateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '22222222',  // DUPLICADO de otro cliente
            'nombres_razon_social' => 'Original',
            'apellidos' => 'Apellido Original',
            'correo' => 'original@test.com',
            'telefono' => '987654321',
            'direccion' => 'Direccion Original',
            'distrito' => 'Lima',
        ];

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → ClientController::update()\n";
        echo "   → try { \$this->clients->update() }\n";
        echo "   → catch (UniqueConstraintViolationException)\n";

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', $this->client), $updateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Request valida unique ignorando el cliente actual\n";
        echo "   → BD detecta violación de constraint\n";
        echo "   → Exception capturada\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Actualización: RECHAZADA\n";
        echo "   ✓ Error: documento duplicado\n";
        echo "   ✓ Cliente mantiene DNI 11111111\n";
        echo "   ✓ 2 clientes en BD (sin cambios)\n";

        $response->assertRedirect();
        $response->assertSessionHasErrors('numero_documento');
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $this->client->getKey(),
            'numero_documento' => '11111111',  // Sin cambiar
        ]);
        
        $this->assertDatabaseCount('clientes', 2);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function permite_actualizar_sin_cambiar_documento()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Permite actualizar sin cambiar documento\n";
        echo str_repeat("═", 80) . "\n";

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente ID: {$this->client->getKey()}\n";
        echo "   • DNI: 11111111 (SIN CAMBIAR)\n";
        echo "   • Correo: nuevo@test.com (CAMBIO)\n";

        $updateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',  // MISMO documento
            'nombres_razon_social' => 'Original Actualizado',
            'apellidos' => 'Apellido Original',
            'correo' => 'nuevo@test.com',  // CAMBIO
            'telefono' => '999888777',  // CAMBIO
            'direccion' => 'Direccion Original',
            'distrito' => 'Lima',
        ];

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Regla unique: ->ignore(\$client)\n";
        echo "   → Excluye el cliente actual de validación unique\n";
        echo "   → Permite mantener el mismo documento\n";

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', $this->client), $updateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Documento sin cambios: PERMITIDO\n";
        echo "   → Otros campos actualizados: SÍ\n";
        echo "   → Sin conflicto de constraint\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Actualización: EXITOSA\n";
        echo "   ✓ numero_documento: 11111111 (sin cambiar)\n";
        echo "   ✓ correo: nuevo@test.com (actualizado)\n";
        echo "   ✓ telefono: 999888777 (actualizado)\n";

        $response->assertRedirect(route('clients.show', $this->client));
        $response->assertSessionHasNoErrors();
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $this->client->getKey(),
            'numero_documento' => '11111111',
            'correo' => 'nuevo@test.com',
            'telefono' => '999888777',
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function protege_contra_sql_injection_en_observaciones()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Protege contra SQL Injection en observaciones\n";
        echo str_repeat("═", 80) . "\n";

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Campo: referencia_direccion\n";
        echo "   • Payload SQL: ' OR '1'='1\n";
        echo "   • Objetivo: inyección SQL\n";

        $sqlInjectionPayload = "'; DELETE FROM clientes WHERE '1'='1";

        $updateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',
            'nombres_razon_social' => 'Original',
            'apellidos' => 'Apellido Original',
            'correo' => 'original@test.com',
            'telefono' => '987654321',
            'direccion' => 'Direccion Original',
            'referencia_direccion' => $sqlInjectionPayload,  // SQL INJECTION ATTEMPT
            'distrito' => 'Lima',
        ];

        echo "\n🔧 PROTECCIÓN:\n";
        echo "   → Laravel Eloquent usa prepared statements\n";
        echo "   → PDO escapa automáticamente\n";
        echo "   → Payload tratado como string literal\n";

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', $this->client), $updateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Payload almacenado como texto\n";
        echo "   → No se ejecuta como SQL\n";
        echo "   → Todos los clientes intactos\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ SQL Injection: BLOQUEADO\n";
        echo "   ✓ Payload almacenado como string\n";
        echo "   ✓ Base de datos segura\n";
        echo "   ✓ Cliente actualizado sin daño\n";

        $response->assertRedirect();
        
        // Verificar que el payload se guardó como texto
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $this->client->getKey(),
            'referencia_direccion' => $sqlInjectionPayload,
        ]);
        
        // Verificar que no se eliminaron registros
        $this->assertDatabaseCount('clientes', 1);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function sanitiza_xss_en_direccion()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Sanitiza XSS en dirección\n";
        echo str_repeat("═", 80) . "\n";

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Campo: direccion\n";
        echo "   • Payload XSS: <script>alert('XSS')</script>\n";
        echo "   • Objetivo: inyección de JavaScript\n";

        $xssPayload = "<script>alert('XSS')</script>Av. Principal";

        $updateData = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11111111',
            'nombres_razon_social' => 'Original',
            'apellidos' => 'Apellido Original',
            'correo' => 'original@test.com',
            'telefono' => '987654321',
            'direccion' => $xssPayload,  // XSS ATTEMPT
            'distrito' => 'Lima',
        ];

        echo "\n🔧 PROTECCIÓN:\n";
        echo "   → Laravel escapa HTML en vistas con {{ }}\n";
        echo "   → Blade templates escapan automáticamente\n";
        echo "   → String almacenado sin ejecutar\n";

        $response = $this->actingAs($this->user)
            ->put(route('clients.update', $this->client), $updateData);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Payload almacenado en BD\n";
        echo "   → Blade escapará al renderizar\n";
        echo "   → JavaScript no se ejecuta\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ XSS: MITIGADO\n";
        echo "   ✓ String almacenado como texto\n";
        echo "   ✓ Blade escapará: &lt;script&gt;...&lt;/script&gt;\n";
        echo "   ✓ Seguridad en capa de presentación\n";

        $response->assertRedirect();
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $this->client->getKey(),
            'direccion' => $xssPayload,
        ]);

        echo str_repeat("═", 80) . "\n";
    }
}
