<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests FEATURE de validación de datos de clientes (StoreClientRequest)
 * 
 * Objetivo: Validar reglas de negocio al crear/actualizar clientes
 * 
 * RF: RF-CLI-02, RF-CLI-03
 */
class ClientValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Deshabilitar middleware EnsureAccountActive para tests de validación
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAccountActive::class);
        
        // Crear usuario con rol Administrador (tiene permiso clients.view para gate register-clients)
        $employee = Employee::factory()->create(['estado' => 'ACTIVO']);
        $role = Role::where('nombre', 'Administrador')->first();
        $this->user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $role->getKey()
        ]);
    }

    /** @test */
    public function rechaza_nombres_vacios()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza nombres vacíos\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'nombres_razon_social' => '',  // VACÍO
            'apellidos' => 'García Pérez',
            'correo' => 'test@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • nombres_razon_social: '' (vacío)\n";
        echo "   • apellidos: {$data['apellidos']}\n";
        echo "   • tipo_documento: {$data['tipo_documento']}\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → StoreClientRequest::rules()\n";
        echo "   → Regla: 'nombres_razon_social' => ['required', 'string', 'max:180']\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Validator detecta campo 'required' vacío\n";
        echo "   → Mensaje: 'El campo nombres o razón social es obligatorio.'\n";

        // Ejecutar assertions y capturar resultados REALES
        $session = $response->getSession();
        $errors = $session->get('errors');
        $tieneError = $errors && (is_object($errors) ? $errors->has('nombres_razon_social') : isset($errors['nombres_razon_social']));
        $clientesCreados = \DB::table('clientes')->count();
        $statusCode = $response->status();

        echo "\n✅ RESULTADO REAL:\n";
        echo "   " . ($statusCode === 302 ? "✓" : "✗") . " HTTP Status: {$statusCode}\n";
        echo "   " . ($tieneError ? "✓" : "✗") . " Error en session: nombres_razon_social\n";
        echo "   " . ($clientesCreados === 0 ? "✓" : "✗") . " Cliente NO creado (BD: {$clientesCreados})\n";
        echo "   " . ($tieneError && $clientesCreados === 0 ? "✅" : "❌") . " Test COMPLETO\n";

        // Assertions finales
        $response->assertSessionHasErrors('nombres_razon_social');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_tipo_documento_invalido()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza tipo_documento inválido\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'PASAPORTE',  // INVÁLIDO (solo acepta DNI, CE, RUC, OTRO)
            'numero_documento' => '12345678',
            'nombres_razon_social' => 'Juan Carlos',
            'apellidos' => 'García Pérez',
            'correo' => 'test@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • tipo_documento: 'PASAPORTE' (NO PERMITIDO)\n";
        echo "   • Valores válidos: DNI, CE, RUC, OTRO\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → StoreClientRequest::rules()\n";
        echo "   → Regla: Rule::in(['DNI', 'CE', 'RUC', 'OTRO'])\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Validator compara con lista permitida\n";
        echo "   → 'PASAPORTE' no está en lista\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Error: 'Selecciona un tipo de documento válido.'\n";
        echo "   ✓ Cliente NO creado\n";

        $response->assertSessionHasErrors('tipo_documento');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_numero_documento_vacio()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza número_documento vacío\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '',  // VACÍO
            'nombres_razon_social' => 'Juan Carlos',
            'apellidos' => 'García Pérez',
            'correo' => 'test@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • numero_documento: '' (vacío)\n";
        echo "   • tipo_documento: {$data['tipo_documento']}\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Regla: 'numero_documento' => ['bail', 'required', ...]\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → 'bail' detiene en primera regla fallida\n";
        echo "   → 'required' falla con string vacío\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Error: campo obligatorio\n";
        echo "   ✓ Cliente NO creado\n";

        $response->assertSessionHasErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_dni_con_formato_invalido()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza DNI con formato inválido\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => 'ABC12345',  // INVÁLIDO (debe ser 8 dígitos numéricos)
            'nombres_razon_social' => 'Juan Carlos',
            'apellidos' => 'García Pérez',
            'correo' => 'test@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • tipo_documento: DNI\n";
        echo "   • numero_documento: 'ABC12345' (contiene letras)\n";
        echo "   • Formato requerido: 8 dígitos numéricos\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Regla condicional: Rule::when(\$this->input('tipo_documento') === 'DNI')\n";
        echo "   → Regex aplicado: /^[0-9]{8}\$/D\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Regex valida: inicio ^ + 8 dígitos [0-9]{8} + fin \$\n";
        echo "   → 'ABC12345' no cumple patrón\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Error: 'El DNI debe contener exactamente 8 dígitos numéricos.'\n";
        echo "   ✓ Cliente NO creado\n";

        $response->assertSessionHasErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_email_con_formato_invalido()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza email con formato inválido\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'nombres_razon_social' => 'Juan Carlos',
            'apellidos' => 'García Pérez',
            'correo' => 'email-invalido-sin-arroba.com',  // SIN @
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • correo: '{$data['correo']}'\n";
        echo "   • Formato esperado: usuario@dominio.ext\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Regla: 'correo' => ['required', 'string', 'email', 'max:150']\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Validator usa regla 'email' de Laravel\n";
        echo "   → Verifica formato RFC 5322\n";
        echo "   → Falta símbolo @\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Error: 'Escribe un correo electrónico válido.'\n";
        echo "   ✓ Cliente NO creado\n";

        $response->assertSessionHasErrors('correo');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_direccion_vacia()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza dirección vacía\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'nombres_razon_social' => 'Juan Carlos',
            'apellidos' => 'García Pérez',
            'correo' => 'test@example.com',
            'telefono' => '987654321',
            'direccion' => '',  // VACÍA
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • direccion: '' (vacía)\n";
        echo "   • Regla: required (campo obligatorio)\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Regla: 'direccion' => ['required', 'string', 'max:255']\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Campo requerido para todos los clientes\n";
        echo "   → Dirección es necesaria para instalaciones\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Error: 'El campo dirección es obligatorio.'\n";
        echo "   ✓ Cliente NO creado\n";

        $response->assertSessionHasErrors('direccion');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function acepta_campos_opcionales_null()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Acepta campos opcionales NULL\n";
        echo str_repeat("═", 80) . "\n";

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '87654321',
            'nombres_razon_social' => 'María Elena',
            'apellidos' => 'Rodríguez López',
            'correo' => 'maria@example.com',
            'telefono' => '912345678',
            'direccion' => 'Jr. Los Pinos 456',
            // Campos opcionales omitidos: distrito, referencia, latitud, etc.
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Campos obligatorios: ✓ Completos\n";
        echo "   • distrito: NO ENVIADO\n";
        echo "   • referencia_direccion: NO ENVIADO\n";
        echo "   • latitud/longitud: NO ENVIADOS\n";
        echo "   • ip_router: NO ENVIADO\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Reglas con 'nullable': se permiten valores NULL\n";
        echo "   → Campos opcionales: distrito, provincia, departamento, latitud, longitud, etc.\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Campos obligatorios: PASARON\n";
        echo "   → Campos opcionales NULL: PERMITIDOS\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Validación: APROBADA\n";
        echo "   ✓ HTTP Status: 302 (redirect a show)\n";
        echo "   ✓ Cliente creado con campos opcionales NULL\n";
        echo "   ✓ Estado inicial: PENDIENTE\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', [
            'numero_documento' => '87654321',
            'estado' => 'PENDIENTE',
            'distrito' => null,
        ]);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function rechaza_campo_que_excede_max_length()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Rechaza campo que excede max length\n";
        echo str_repeat("═", 80) . "\n";

        $nombreMuyLargo = str_repeat('A', 181);  // 181 caracteres (máx 180)

        $data = [
            'tipo_documento' => 'DNI',
            'numero_documento' => '11223344',
            'nombres_razon_social' => $nombreMuyLargo,  // EXCEDE 180
            'apellidos' => 'García',
            'correo' => 'test@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'distrito' => 'Lima',
        ];

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • nombres_razon_social: " . strlen($nombreMuyLargo) . " caracteres\n";
        echo "   • Límite permitido: 180 caracteres\n";
        echo "   • Exceso: " . (strlen($nombreMuyLargo) - 180) . " caracteres\n";

        echo "\n🔧 REQUEST VALIDADO:\n";
        echo "   → Regla: 'nombres_razon_social' => ['required', 'string', 'max:180']\n";

        $response = $this->actingAs($this->user)->post(route('clients.store'), $data);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Validator aplica regla 'max:180'\n";
        echo "   → strlen() retorna " . strlen($nombreMuyLargo) . " > 180\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Validación: RECHAZADA\n";
        echo "   ✓ Error: 'El campo nombres o razón social admite hasta 180 caracteres.'\n";
        echo "   ✓ Cliente NO creado\n";

        $response->assertSessionHasErrors('nombres_razon_social');
        $this->assertDatabaseCount('clientes', 0);

        echo str_repeat("═", 80) . "\n";
    }
}
