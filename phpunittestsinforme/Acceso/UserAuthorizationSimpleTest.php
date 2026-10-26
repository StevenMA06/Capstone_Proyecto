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

    /**
     * Data Provider: Múltiples casos de usuario inactivo con diferentes roles
     */
    public static function usuarioInactivoProvider(): array
    {
        return [
            'Admin inactivo' => [
                'rol' => 'Administrador',
                'cargo' => 'Administrador',
                'estado' => 'INACTIVO',
                'permiso' => 'contracts.create'
            ],
            'Vendedor inactivo' => [
                'rol' => Role::OFFICE,
                'cargo' => 'Vendedor',
                'estado' => 'INACTIVO',
                'permiso' => 'clients.view'
            ],
            'Técnico inactivo' => [
                'rol' => Role::TECHNICIAN,
                'cargo' => 'Técnico',
                'estado' => 'INACTIVO',
                'permiso' => 'installation_orders.view'
            ],
            'Gerente inactivo' => [
                'rol' => 'Administrador',
                'cargo' => 'Gerente',
                'estado' => 'INACTIVO',
                'permiso' => 'payments.complete'
            ],
            'Almacenero inactivo' => [
                'rol' => Role::WAREHOUSE,
                'cargo' => 'Almacenero',
                'estado' => 'INACTIVO',
                'permiso' => 'materials.view'
            ],
        ];
    }

    /** 
     * @test 
     * @dataProvider usuarioInactivoProvider
     */
    public function usuario_inactivo_no_puede_acceder_incluso_con_permisos($rol, $cargo, $estado, $permiso)
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Usuario INACTIVO no puede acceder\n";
        echo str_repeat("═", 80) . "\n";

        // Crear empleado INACTIVO
        $employee = Employee::factory()->create([
            'estado' => $estado,
            'cargo' => $cargo
        ]);
        
        // Crear usuario con rol
        $role = Role::where('nombre', $rol)->first();
        $user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $role->getKey()
        ]);

        echo "\n📥 DATOS DE ENTRADA (Ciclo actual):\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Rol: {$rol}\n";
        echo "   • Cargo: {$cargo}\n";
        echo "   • Estado empleado: {$estado}\n";
        echo "   • Permiso consultado: {$permiso}\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::canAccess()\n";
        echo "   → User::hasPermission('{$permiso}')\n";

        $canAccess = $user->canAccess();
        $hasPermission = $user->hasPermission($permiso);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → hasPermission() verifica canAccess() primero\n";
        echo "   → canAccess() retorna false si empleado INACTIVO\n";
        echo "   → hasPermission() retorna false automáticamente\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ canAccess(): " . ($canAccess ? 'true' : 'false') . "\n";
        echo "   ✗ hasPermission(): " . ($hasPermission ? 'true' : 'false') . "\n";
        echo "   ✓ Usuario INACTIVO bloqueado correctamente ({$rol})\n";

        $this->assertFalse($canAccess, 'Usuario inactivo no debe poder acceder');
        $this->assertFalse($hasPermission, 'Usuario inactivo no debe tener permisos');

        echo str_repeat("═", 80) . "\n";
    }

    /**
     * Data Provider: Múltiples permisos para validar acceso admin
     */
    public static function permisosAdminProvider(): array
    {
        return [
            'Permisos de contratos' => [
                'permisos' => ['contracts.create', 'contracts.view', 'contracts.update', 'contracts.reactivate']
            ],
            'Permisos de clientes' => [
                'permisos' => ['clients.create', 'clients.view', 'clients.update', 'clients.status']
            ],
            'Permisos de pagos' => [
                'permisos' => ['payments.view', 'payments.complete', 'payments.create', 'payments.cancel']
            ],
            'Permisos de órdenes' => [
                'permisos' => ['orders.view', 'orders.schedule', 'orders.cancel']
            ],
            'Permisos de técnicos' => [
                'permisos' => ['technician.view', 'technician.supervise', 'technician.start']
            ],
            'Permisos de materiales' => [
                'permisos' => ['materials.create', 'materials.view', 'materials.entry', 'materials.exit']
            ],
        ];
    }

    /** 
     * @test 
     * @dataProvider permisosAdminProvider
     */
    public function usuario_activo_con_rol_admin_tiene_todos_los_permisos($permisos)
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

        echo "\n📥 DATOS DE ENTRADA (Ciclo actual):\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Rol: Administrador\n";
        echo "   • Estado empleado: ACTIVO\n";
        echo "   • Permisos a validar: " . count($permisos) . "\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::isAdministrator()\n";
        echo "   → User::hasPermission() [" . count($permisos) . " permisos]\n";

        $isAdmin = $user->isAdministrator();
        
        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → isAdministrator() retorna true\n";
        echo "   → hasPermission() siempre retorna true para admin\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✓ isAdministrator(): " . ($isAdmin ? 'true' : 'false') . "\n";
        
        foreach ($permisos as $permiso) {
            $tiene = $user->hasPermission($permiso);
            echo "   ✓ {$permiso}: " . ($tiene ? 'true' : 'false') . "\n";
            $this->assertTrue($tiene, "Admin debe tener permiso: {$permiso}");
        }
        
        echo "   ✓ Administrador tiene acceso total (" . count($permisos) . " permisos validados)\n";

        $this->assertTrue($isAdmin);

        echo str_repeat("═", 80) . "\n";
    }

    /**
     * Data Provider: Múltiples permisos inexistentes
     */
    public static function permisoInexistenteProvider(): array
    {
        return [
            'Permiso con typo' => [
                'permiso' => 'contracts.craete',  // typo: craete en lugar de create
                'motivo' => 'Error de escritura'
            ],
            'Permiso inventado' => [
                'permiso' => 'unicorns.fly',
                'motivo' => 'Módulo inexistente'
            ],
            'Permiso con formato incorrecto' => [
                'permiso' => 'CONTRACTS-CREATE',
                'motivo' => 'Formato incorrecto (- en lugar de .)'
            ],
            'Permiso vacío' => [
                'permiso' => '',
                'motivo' => 'String vacío'
            ],
            'Permiso con espacios' => [
                'permiso' => 'contracts. create',
                'motivo' => 'Espacios en el código'
            ],
            'Permiso SQL injection' => [
                'permiso' => "'; DROP TABLE users--",
                'motivo' => 'Intento de inyección SQL'
            ],
            'Permiso solo puntos' => [
                'permiso' => '...',
                'motivo' => 'Formato inválido'
            ],
        ];
    }

    /** 
     * @test 
     * @dataProvider permisoInexistenteProvider
     */
    public function permiso_inexistente_retorna_false($permiso, $motivo)
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

        echo "\n📥 DATOS DE ENTRADA (Ciclo actual):\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Rol: Administrador (todos los permisos)\n";
        echo "   • Permiso consultado: " . var_export($permiso, true) . "\n";
        echo "   • Motivo del test: {$motivo}\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::hasPermission('" . addslashes($permiso) . "')\n";

        $result = $user->hasPermission($permiso);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → hasPermission() verifica si el código existe en AccessCatalog\n";
        echo "   → Si no existe en catálogo, retorna false (incluso para admin)\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ Permiso encontrado: NO\n";
        echo "   ✓ Retorna false correctamente ({$motivo})\n";
        echo "   ✓ Sistema protegido contra códigos inválidos\n";

        $this->assertFalse($result);

        echo str_repeat("═", 80) . "\n";
    }

    /**
     * Data Provider: Múltiples escenarios de fecha de cese
     */
    public static function fechaCeseProvider(): array
    {
        return [
            'Cese hace 5 días' => [
                'dias_atras' => 5,
                'cargo' => 'Gerente',
                'rol' => 'Administrador',
                'permiso' => 'contracts.create'
            ],
            'Cese hace 1 mes' => [
                'dias_atras' => 30,
                'cargo' => 'Vendedor',
                'rol' => Role::OFFICE,
                'permiso' => 'clients.view'
            ],
            'Cese hace 3 meses' => [
                'dias_atras' => 90,
                'cargo' => 'Técnico',
                'rol' => Role::TECHNICIAN,
                'permiso' => 'installation_orders.view'
            ],
            'Cese ayer' => [
                'dias_atras' => 1,
                'cargo' => 'Administrador',
                'rol' => 'Administrador',
                'permiso' => 'users.manage'
            ],
            'Cese hace 1 año' => [
                'dias_atras' => 365,
                'cargo' => 'Almacenero',
                'rol' => Role::WAREHOUSE,
                'permiso' => 'materials.view'
            ],
        ];
    }

    /** 
     * @test 
     * @dataProvider fechaCeseProvider
     */
    public function usuario_con_fecha_cese_no_puede_acceder($dias_atras, $cargo, $rol, $permiso)
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "🔒 TEST: Usuario con fecha_cese no puede acceder\n";
        echo str_repeat("═", 80) . "\n";

        // Crear empleado con fecha de cese
        $fechaCese = now()->subDays($dias_atras);
        $employee = Employee::factory()->create([
            'estado' => 'ACTIVO',
            'fecha_cese' => $fechaCese,
            'cargo' => $cargo
        ]);
        
        $role = Role::where('nombre', $rol)->first();
        $user = User::factory()->create([
            'empleado_id' => $employee->getKey(),
            'rol_id' => $role->getKey()
        ]);

        echo "\n📥 DATOS DE ENTRADA (Ciclo actual):\n";
        echo "   • Usuario ID: {$user->getKey()}\n";
        echo "   • Estado empleado: ACTIVO\n";
        echo "   • Cargo: {$cargo}\n";
        echo "   • Rol: {$rol}\n";
        echo "   • fecha_cese: {$fechaCese->format('Y-m-d')} (hace {$dias_atras} días)\n";
        echo "   • Permiso: {$permiso}\n";

        echo "\n🔧 MÉTODO EJECUTADO:\n";
        echo "   → User::canAccess()\n";
        echo "   → User::hasPermission('{$permiso}')\n";

        $canAccess = $user->canAccess();
        $hasPermission = $user->hasPermission($permiso);

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Empleado tiene fecha_cese válida\n";
        echo "   → canAccess() verifica que el empleado esté vigente\n";
        echo "   → hasPermission() retorna false si no puede acceder\n";

        echo "\n✅ RESULTADO:\n";
        echo "   ✗ canAccess(): " . ($canAccess ? 'true' : 'false') . "\n";
        echo "   ✗ hasPermission(): " . ($hasPermission ? 'true' : 'false') . "\n";
        echo "   ✓ Usuario cesado bloqueado correctamente ({$cargo}, hace {$dias_atras} días)\n";

        $this->assertFalse($canAccess);
        $this->assertFalse($hasPermission);

        echo str_repeat("═", 80) . "\n";
    }
}
