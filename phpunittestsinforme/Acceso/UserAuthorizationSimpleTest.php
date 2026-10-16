<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests UNITARIOS del método hasPermission() del modelo User
 * 
 * Objetivo: Validar autorización básica usando permisos existentes del sistema
 * 
 * RF: RF-ACC-15
 */
class UserAuthorizationSimpleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function usuario_inactivo_no_puede_acceder_incluso_con_permisos()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Usuario INACTIVO no puede acceder\n";
        echo str_repeat("═", 80) . "\n";

        // Crear empleado INACTIVO
        $employee = Employee::factory()->create([
            'estado' => 'INACTIVO',
            'cargo' => 'Administrador'
        ]);
        
        // Crear usuario con rol admin
        $adminRole = Role::where('nombre', 'Administrador')->first();
        $user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $adminRole->getKey()
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Rol: Administrador (tiene todos los permisos)\n";
        echo "   • Estado empleado: INACTIVO\n";
        echo "   • Permiso consultado: contracts.create\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::canAccess()\n";
        echo "   → User::hasPermission('contracts.create')\n";

        $canAccess = $user->canAccess();
        $hasPermission = $user->hasPermission('contracts.create');

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → hasPermission() verifica canAccess() primero\n";
        echo "   → canAccess() retorna false si empleado INACTIVO\n";
        echo "   → hasPermission() retorna false automáticamente\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ canAccess(): " . ($canAccess ? 'true' : 'false') . "\n";
        echo "   ✗ hasPermission(): " . ($hasPermission ? 'true' : 'false') . "\n";
        echo "   ✓ Usuario INACTIVO bloqueado correctamente\n";

        $this->assertFalse($canAccess, 'Usuario inactivo no debe poder acceder');
        $this->assertFalse($hasPermission, 'Usuario inactivo no debe tener permisos');

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function usuario_activo_con_rol_admin_tiene_todos_los_permisos()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Usuario ACTIVO con rol Admin tiene todos los permisos\n";
        echo str_repeat("═", 80) . "\n";

        // Crear empleado ACTIVO
        $employee = Employee::factory()->create([
            'estado' => 'ACTIVO',
            'cargo' => 'Administrador'
        ]);
        
        // Crear usuario admin
        $adminRole = Role::where('nombre', 'Administrador')->first();
        $user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $adminRole->getKey()
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Rol: Administrador\n";
        echo "   • Estado empleado: ACTIVO\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::isAdministrator()\n";
        echo "   → User::hasPermission() [múltiples permisos]\n";

        $isAdmin = $user->isAdministrator();
        $perm1 = $user->hasPermission('contracts.create');
        $perm2 = $user->hasPermission('clients.view');
        $perm3 = $user->hasPermission('payments.complete');

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → isAdministrator() retorna true\n";
        echo "   → hasPermission() siempre retorna true para admin\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ isAdministrator(): " . ($isAdmin ? 'true' : 'false') . "\n";
        echo "   ✓ contracts.create: " . ($perm1 ? 'true' : 'false') . "\n";
        echo "   ✓ clients.view: " . ($perm2 ? 'true' : 'false') . "\n";
        echo "   ✓ payments.complete: " . ($perm3 ? 'true' : 'false') . "\n";
        echo "   ✓ Administrador tiene acceso total\n";

        $this->assertTrue($isAdmin);
        $this->assertTrue($perm1);
        $this->assertTrue($perm2);
        $this->assertTrue($perm3);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function permiso_inexistente_retorna_false()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Permiso inexistente retorna false\n";
        echo str_repeat("═", 80) . "\n";

        // Crear usuario admin activo
        $employee = Employee::factory()->create(['estado' => 'ACTIVO']);
        $adminRole = Role::where('nombre', 'Administrador')->first();
        $user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $adminRole->getKey()
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Rol: Administrador (todos los permisos)\n";
        echo "   • Permiso consultado: permiso.que.no.existe.en.catalogo\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::hasPermission('permiso.que.no.existe.en.catalogo')\n";

        $result = $user->hasPermission('permiso.que.no.existe.en.catalogo');

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → hasPermission() verifica si el código existe en AccessCatalog\n";
        echo "   → Si no existe en catálogo, retorna false (incluso para admin)\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Permiso encontrado: NO\n";
        echo "   ✓ Retorna false correctamente\n";
        echo "   ✓ Sistema protegido contra códigos inválidos\n";

        $this->assertFalse($result);

        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function usuario_con_fecha_cese_no_puede_acceder()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Usuario con fecha_cese no puede acceder\n";
        echo str_repeat("═", 80) . "\n";

        // Crear empleado con fecha de cese
        $fechaCese = now()->subDays(5);
        $employee = Employee::factory()->create([
            'estado' => 'ACTIVO',
            'fecha_cese' => $fechaCese,
            'cargo' => 'Gerente'
        ]);
        
        $adminRole = Role::where('nombre', 'Administrador')->first();
        $user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $adminRole->getKey()
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Estado empleado: ACTIVO\n";
        echo "   • fecha_cese: {$fechaCese->format('Y-m-d')} (hace 5 días)\n";
        echo "   • Rol: Administrador\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::canAccess()\n";
        echo "   → User::hasPermission('contracts.create')\n";

        $canAccess = $user->canAccess();
        $hasPermission = $user->hasPermission('contracts.create');

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Empleado tiene fecha_cese válida\n";
        echo "   → canAccess() verifica que el empleado esté vigente\n";
        echo "   → hasPermission() retorna false si no puede acceder\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ canAccess(): " . ($canAccess ? 'true' : 'false') . "\n";
        echo "   ✗ hasPermission(): " . ($hasPermission ? 'true' : 'false') . "\n";
        echo "   ✓ Usuario cesado bloqueado correctamente\n";

        $this->assertFalse($canAccess);
        $this->assertFalse($hasPermission);

        echo str_repeat("═", 80) . "\n";
    }
}
