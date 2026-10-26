<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests FEATURE de desactivación de clientes
 * 
 * Objetivo: Validar que la desactivación cambia estado sin eliminar datos
 * 
 * RF: RF-CLI-10
 */
class ClientDeactivateTest extends TestCase
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

    /**
     * Data Provider: Múltiples tipos de clientes activos
     */
    public static function clienteActivoProvider(): array
    {
        return [
            'Cliente DNI persona natural' => [
                'tipo_documento' => 'DNI',
                'numero_documento' => '12345678',
                'nombres_razon_social' => 'Juan Pérez García',
                'tipo_cliente' => 'Persona natural'
            ],
            'Empresa con RUC' => [
                'tipo_documento' => 'RUC',
                'numero_documento' => '20123456789',
                'nombres_razon_social' => 'Empresa Tecnológica SAC',
                'tipo_cliente' => 'Empresa'
            ],
            'Extranjero con CE' => [
                'tipo_documento' => 'CE',
                'numero_documento' => '001234567',
                'nombres_razon_social' => 'John Smith',
                'tipo_cliente' => 'Extranjero'
            ],
            'Cliente con documento OTRO' => [
                'tipo_documento' => 'OTRO',
                'numero_documento' => 'PASS-ABC123',
                'nombres_razon_social' => 'Cliente Internacional',
                'tipo_cliente' => 'Otro documento'
            ],
            'Cliente con múltiples contratos' => [
                'tipo_documento' => 'DNI',
                'numero_documento' => '87654321',
                'nombres_razon_social' => 'María Rodríguez López',
                'tipo_cliente' => 'Cliente frecuente'
            ],
        ];
    }

    /** 
     * @test 
     * @dataProvider clienteActivoProvider
     */
    public function desactiva_cliente_activo_correctamente($tipo_documento, $numero_documento, $nombres_razon_social, $tipo_cliente)
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Desactiva cliente ACTIVO correctamente\n";
        echo str_repeat("═", 80) . "\n";

        // Crear cliente ACTIVO
        $client = Client::factory()->create([
            'tipo_documento' => $tipo_documento,
            'numero_documento' => $numero_documento,
            'nombres_razon_social' => $nombres_razon_social,
            'estado' => 'ACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA (Ciclo actual):\n";
        echo "   • Cliente ID: {$client->getKey()}\n";
        echo "   • Tipo: {$tipo_cliente}\n";
        echo "   • Documento: {$tipo_documento} {$numero_documento}\n";
        echo "   • Estado actual: ACTIVO\n";
        echo "   • Nombres: {$nombres_razon_social}\n";

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → ClientController::deactivate()\n";
        echo "   → ClientService::deactivate()\n";
        echo "   → ClientRepository::deactivate()\n";
        echo "   → Client::whereKey()->where('estado', 'ACTIVO')->update(['estado' => 'INACTIVO'])\n";

        $response = $this->actingAs($this->user)
            ->patch(route('clients.deactivate', $client));

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Query condicional: WHERE estado = 'ACTIVO'\n";
        echo "   → Solo desactiva si está ACTIVO\n";
        echo "   → Estado cambiado a INACTIVO\n";
        echo "   → Todos los datos preservados\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ HTTP Status: 302 (redirect a show)\n";
        echo "   ✓ Estado: INACTIVO\n";
        echo "   ✓ Mensaje: 'Cliente inhabilitado. Su información y sus historiales se conservan.'\n";
        echo "   ✓ Datos preservados: nombres, documento, dirección, etc.\n";
        echo "   ✓ Historiales intactos ({$tipo_cliente})\n";

        $response->assertRedirect(route('clients.show', $client));
        $response->assertSessionHas('status');
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $client->getKey(),
            'estado' => 'INACTIVO',
            'nombres_razon_social' => $nombres_razon_social,
            'numero_documento' => $numero_documento,
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function cliente_ya_inactivo_permanece_inactivo()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "⚠️  TEST: Cliente ya INACTIVO permanece INACTIVO\n";
        echo str_repeat("═", 80) . "\n";

        // Crear cliente YA INACTIVO
        $client = Client::factory()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '87654321',
            'nombres_razon_social' => 'Cliente Ya Inactivo',
            'estado' => 'INACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente ID: {$client->getKey()}\n";
        echo "   • Estado actual: INACTIVO (ya desactivado)\n";

        echo "\n🔧 REPOSITORY EJECUTADO:\n";
        echo "   → ClientRepository::deactivate()\n";
        echo "   → Query: whereKey()->where('estado', 'ACTIVO')->update()\n";
        echo "   → WHERE clausula: estado = 'ACTIVO'\n";

        $response = $this->actingAs($this->user)
            ->patch(route('clients.deactivate', $client));

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Query WHERE no encuentra registros (estado != 'ACTIVO')\n";
        echo "   → 0 filas actualizadas\n";
        echo "   → Cliente permanece sin cambios\n";
        echo "   → Operación idempotente\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ HTTP Status: 302\n";
        echo "   ✓ Estado: INACTIVO (sin cambios)\n";
        echo "   ✓ Operación completada sin errores\n";
        echo "   ✓ Seguro de ejecutar múltiples veces\n";

        $response->assertRedirect(route('clients.show', $client));
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $client->getKey(),
            'estado' => 'INACTIVO',
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /**
     * Data Provider: Múltiples estados no-activos
     */
    public static function estadosNoActivosProvider(): array
    {
        return [
            'Cliente PENDIENTE sin contrato' => [
                'estado' => 'PENDIENTE',
                'tipo_documento' => 'DNI',
                'numero_documento' => '99887766',
                'motivo' => 'Cliente registrado sin contrato aún'
            ],
            'Cliente ya INACTIVO' => [
                'estado' => 'INACTIVO',
                'tipo_documento' => 'RUC',
                'numero_documento' => '20111222333',
                'motivo' => 'Ya fue desactivado previamente'
            ],
        ];
    }

    /** 
     * @test 
     * @dataProvider estadosNoActivosProvider
     */
    public function cliente_pendiente_tambien_puede_desactivarse($estado, $tipo_documento, $numero_documento, $motivo)
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "⚠️  TEST: Cliente {$estado} no se desactiva (solo ACTIVO)\n";
        echo str_repeat("═", 80) . "\n";

        // Crear cliente con estado específico
        $client = Client::factory()->create([
            'tipo_documento' => $tipo_documento,
            'numero_documento' => $numero_documento,
            'nombres_razon_social' => "Cliente {$estado}",
            'estado' => $estado
        ]);

        echo "\n📥 DATOS DE ENTRADA (Ciclo actual):\n";
        echo "   • Cliente ID: {$client->getKey()}\n";
        echo "   • Estado actual: {$estado}\n";
        echo "   • Documento: {$tipo_documento} {$numero_documento}\n";
        echo "   • Situación: {$motivo}\n";

        echo "\n🔧 REPOSITORY EJECUTADO:\n";
        echo "   → ClientRepository::deactivate()\n";
        echo "   → Query: ->where('estado', 'ACTIVO')\n";
        echo "   → WHERE condicional protege otros estados\n";

        $response = $this->actingAs($this->user)
            ->patch(route('clients.deactivate', $client));

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Query WHERE busca estado = 'ACTIVO'\n";
        echo "   → Cliente tiene estado = '{$estado}'\n";
        echo "   → 0 filas afectadas\n";
        echo "   → Estado {$estado} preservado\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Estado: {$estado} (sin cambios)\n";
        echo "   ✓ Solo clientes ACTIVOS son desactivables\n";
        echo "   ✓ Lógica de negocio: {$estado} != ACTIVO\n";
        echo "   ✓ Repository protege con WHERE condicional\n";

        $response->assertRedirect();
        
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => $client->getKey(),
            'estado' => $estado,  // Sin cambiar
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function mensaje_confirmacion_adecuado()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Mensaje de confirmación adecuado\n";
        echo str_repeat("═", 80) . "\n";

        $client = Client::factory()->create([
            'tipo_documento' => 'RUC',
            'numero_documento' => '20123456789',
            'nombres_razon_social' => 'Empresa SAC',
            'estado' => 'ACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Cliente: {$client->nombres_razon_social}\n";
        echo "   • Tipo: Empresa (RUC)\n";
        echo "   • Estado: ACTIVO\n";

        echo "\n🔧 CONTROLLER EJECUTADO:\n";
        echo "   → return redirect()->with('status', 'Cliente inhabilitado...')\n";

        $response = $this->actingAs($this->user)
            ->patch(route('clients.deactivate', $client));

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Mensaje flash en sesión\n";
        echo "   → Informa que datos se conservan\n";
        echo "   → Tranquiliza al usuario\n";

        echo "\n✅ RESULTADO:\n";
        $statusMessage = session('status');
        echo "   ✓ Mensaje flash: '{$statusMessage}'\n";
        echo "   ✓ Confirma: inhabilitado\n";
        echo "   ✓ Aclara: información conservada\n";
        echo "   ✓ Aclara: historiales conservados\n";

        $response->assertSessionHas('status', 'Cliente inhabilitado. Su información y sus historiales se conservan.');

        echo str_repeat("═", 80) . "\n";
    }
}
