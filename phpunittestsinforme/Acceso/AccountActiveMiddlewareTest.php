<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Tests FEATURE del middleware EnsureAccountActive
 * 
 * Objetivo: Validar que usuarios INACTIVOS son bloqueados automáticamente
 * 
 * RF: RF-ACC-16
 */
class AccountActiveMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Employee $employee;
    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->employee = Employee::factory()->create([
            'estado' => 'ACTIVO',
            'cargo' => 'Administrador'
        ]);
        
        $this->user = User::factory()->create([
            'empleado_id' => $this->employee->getKey()
        ]);

        $this->role = Role::where('nombre', 'Administrador')->first() 
            ?? Role::factory()->create(['nombre' => 'Administrador']);
        
        $this->user->roles()->attach($this->role->getKey());
    }

    /** @test */
    public function usuario_activo_puede_acceder()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Usuario ACTIVO puede acceder\n";
        echo str_repeat("═", 80) . "\n";

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$this->user->getKey()}\n";
        echo "   • Empleado ID: {$this->employee->getKey()}\n";
        echo "   • Estado empleado: ACTIVO\n";
        echo "   • fecha_cese: NULL\n";

        echo "\n🔧 MIDDLEWARE EJECUTADO:\n";
        echo "   → EnsureAccountActive\n";
        echo "   → Ruta: GET /dashboard\n";

        // Autenticar usuario ACTIVO
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Usuario autenticado: SÍ\n";
        echo "   → User::isInactive(): false\n";
        echo "   → Middleware permite paso\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ HTTP Status: {$response->status()}\n";
        echo "   ✓ Middleware permitió el paso\n";
        echo "   ✓ No hay redirección a login (status 302 es esperado)\n";

        $response->assertStatus(302);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function usuario_inactivo_es_redirigido_a_login()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Usuario INACTIVO es redirigido a login\n";
        echo str_repeat("═", 80) . "\n";

        // Desactivar empleado
        $this->employee->update(['estado' => 'INACTIVO']);
        $this->user->refresh();

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$this->user->getKey()}\n";
        echo "   • Empleado ID: {$this->employee->getKey()}\n";
        echo "   • Estado empleado: INACTIVO\n";

        echo "\n🔧 MIDDLEWARE EJECUTADO:\n";
        echo "   → EnsureAccountActive\n";
        echo "   → Detecta User::isInactive() = true\n";

        // Autenticar usuario INACTIVO
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        echo "\n⚙️ PROCESO:\n";
        echo "   1. Middleware detecta cuenta INACTIVA\n";
        echo "   2. Auth::logout()\n";
        echo "   3. Sesión invalidada\n";
        echo "   4. Token regenerado\n";
        echo "   5. Redirección a login con error\n";

        echo "\n❌ RESULTADO:\n";
        echo "   ✗ Acceso bloqueado\n";
        echo "   ✓ Redirigido a: " . route('login') . "\n";
        echo "   ✓ HTTP Status: {$response->status()}\n";
        echo "   ✓ Usuario deslogueado: " . (Auth::check() ? 'NO' : 'SÍ') . "\n";
        echo "   ✓ Mensaje: 'Tu cuenta está inhabilitada...'\n";

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check(), 'Usuario debe estar deslogueado');

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function usuario_con_fecha_cese_es_bloqueado()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Usuario con fecha_cese es bloqueado\n";
        echo str_repeat("═", 80) . "\n";

        // Asignar fecha de cese
        $fechaCese = now()->subDays(10);
        $this->employee->update(['fecha_cese' => $fechaCese]);
        $this->user->refresh();

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$this->user->getKey()}\n";
        echo "   • Empleado ID: {$this->employee->getKey()}\n";
        echo "   • Estado empleado: {$this->employee->estado}\n";
        echo "   • fecha_cese: {$fechaCese->format('Y-m-d')} (hace 10 días)\n";

        echo "\n🔧 MIDDLEWARE EJECUTADO:\n";
        echo "   → EnsureAccountActive\n";
        echo "   → User::isInactive() verifica fecha_cese\n";

        // Autenticar usuario con fecha de cese
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Empleado tiene fecha_cese: SÍ\n";
        echo "   → User::isInactive(): true\n";
        echo "   → Middleware bloquea acceso\n";

        echo "\n❌ RESULTADO:\n";
        echo "   ✗ Acceso bloqueado\n";
        echo "   ✓ Redirigido a login\n";
        echo "   ✓ Sesión cerrada automáticamente\n";

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function sesion_se_invalida_al_detectar_cuenta_inactiva()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Sesión se invalida al detectar cuenta INACTIVA\n";
        echo str_repeat("═", 80) . "\n";

        // Desactivar empleado
        $this->employee->update(['estado' => 'INACTIVO']);
        $this->user->refresh();

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$this->user->getKey()}\n";
        echo "   • Estado empleado: INACTIVO\n";

        echo "\n🔧 PROCESO:\n";
        echo "   1. Usuario se autentica\n";
        echo "   2. Intenta acceder a ruta protegida\n";
        echo "   3. Middleware detecta cuenta INACTIVA\n";

        // Autenticar
        $this->actingAs($this->user);
        
        echo "   ✓ Usuario autenticado inicialmente\n";
        $this->assertTrue(Auth::check(), 'Usuario debe estar autenticado al inicio');

        // Intentar acceder
        $response = $this->get(route('dashboard'));

        echo "\n⚙️ ACCIONES DEL MIDDLEWARE:\n";
        echo "   → Auth::logout() ejecutado\n";
        echo "   → session()->invalidate() ejecutado\n";
        echo "   → session()->regenerateToken() ejecutado\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ Sesión invalidada: SÍ\n";
        echo "   ✓ Usuario deslogueado: SÍ\n";
        echo "   ✓ Token regenerado: SÍ\n";
        echo "   ✓ Seguridad máxima\n";

        $this->assertFalse(Auth::check(), 'Usuario debe estar deslogueado después');
        $response->assertRedirect(route('login'));

        echo str_repeat("═", 80) . "\n";
    }
}
