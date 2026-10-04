<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynamicPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')]);
    }

    private function custom(User $manager, string $name, array $permissions): Role
    {
        return app(RoleAccessService::class)->saveRole($manager, null, ['nombre' => $name, 'descripcion' => 'Rol de prueba', 'estado' => 'ACTIVO', 'permissions' => $permissions]);
    }

    public function test_multiple_roles_union_revocation_and_inactive_roles_are_applied_immediately(): void
    {
        $manager = $this->manager();
        $a = $this->custom($manager, 'Consultas', ['clients.view']);
        $b = $this->custom($manager, 'Almacén', ['clients.view', 'materials.view']);
        $user = User::factory()->create(['rol_id' => $a->getKey()]);
        $service = app(RoleAccessService::class);
        $service->assign($manager, $user, [$a->getKey(), $b->getKey()]);
        $this->signIn($user)->get('/materials')->assertOk();
        $this->get('/clients')->assertOk()->assertDontSee('href="'.route('users.index').'"', false);
        $this->get('/clients/create')->assertForbidden();
        $this->post('/clients', [])->assertForbidden();
        $service->assign($manager, $user, [$a->getKey()]);
        $this->get('/materials')->assertForbidden();
        $this->get('/clients')->assertOk();
        $service->saveRole($manager, $a, ['nombre' => $a->nombre, 'descripcion' => 'Sin permisos', 'estado' => 'ACTIVO', 'permissions' => []]);
        $this->get('/clients')->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertSee('solicita a Gerencia');
        $a->update(['estado' => 'INACTIVO']);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertDatabaseHas('auditoria_permisos', ['actor_id' => $manager->getKey(), 'accion' => 'ASIGNAR_ROLES', 'entidad_id' => $user->getKey()]);
    }

    public function test_inventory_urls_and_mutations_are_denied_to_technicians_and_office(): void
    {
        foreach ([Role::TECHNICIAN, Role::OFFICE] as $name) {
            $user = User::factory()->create(['rol_id' => Role::where('nombre', $name)->value('rol_id')]);
            $this->signIn($user);
            foreach (['equipments', 'materials', 'kardex', 'roles', 'user-roles'] as $path) $this->get('/'.$path)->assertForbidden();
            foreach (['equipments', 'materials', 'roles'] as $path) $this->post('/'.$path, [])->assertForbidden();
        }
    }

    public function test_management_screens_validate_save_and_preserve_protected_roles(): void
    {
        $manager = $this->manager(); $this->signIn($manager);
        $this->get('/roles')->assertOk()->assertSee('Roles y permisos');
        $this->get('/user-roles')->assertOk();
        $this->post('/roles', ['nombre' => 'Solo consulta', 'descripcion' => 'Consulta del inventario', 'estado' => 'ACTIVO', 'permissions' => ['equipments.view']])->assertSessionHasNoErrors()->assertRedirect();
        $role = Role::where('nombre', 'Solo consulta')->firstOrFail();
        $this->get('/roles?role='.$role->getKey())->assertOk()->assertSee('Consulta del inventario');
        $this->post('/roles', ['nombre' => 'Solo consulta', 'descripcion' => 'Duplicado', 'estado' => 'ACTIVO'])->assertSessionHasErrors('nombre');
        $this->post('/roles', ['nombre' => 'Inválido', 'descripcion' => 'Sin consulta', 'estado' => 'ACTIVO', 'permissions' => ['materials.manage']])->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('roles', ['nombre' => 'Inválido']);
        $adminRole = Role::where('nombre', Role::ADMIN)->firstOrFail();
        $this->put('/roles/'.$adminRole->getKey(), ['nombre' => Role::ADMIN, 'descripcion' => 'Cambio', 'estado' => 'INACTIVO'])->assertSessionHasErrors('nombre');
        $admin = User::factory()->create(['rol_id' => $adminRole->getKey()]);
        $this->put('/user-roles/'.$admin->getKey(), ['roles' => [$role->getKey()]])->assertForbidden();
        $this->signIn($admin)->put('/user-roles/'.$admin->getKey(), ['roles' => [$role->getKey()]])->assertSessionHasErrors('roles');
    }

    public function test_last_effective_administrator_cannot_remove_own_access(): void
    {
        $manager = $this->manager();
        $role = $this->custom($manager, 'Administración delegada', ['users.view','users.manage','roles.view','roles.manage','user-roles.view','user-roles.manage']);
        $user = User::factory()->create(['rol_id' => $role->getKey()]);
        $manager->update(['estado' => 'INACTIVO']);
        $this->signIn($user)->put('/roles/'.$role->getKey(), ['nombre' => $role->nombre, 'descripcion' => 'Cambio', 'estado' => 'INACTIVO', 'permissions' => []])->assertSessionHasErrors('roles');
        $this->assertTrue($user->hasPermission('roles.manage'));
        $this->patch('/users/'.$user->getKey().'/status', ['is_active' => false])->assertSessionHasErrors('roles');
    }

    public function test_inactive_roles_cannot_be_assigned_and_primary_role_is_migrated(): void
    {
        $manager = $this->manager();
        $user = User::factory()->create();
        $this->assertDatabaseHas('usuario_rol', ['usuario_id' => $user->getKey(), 'rol_id' => $user->rol_id]);
        $role = $this->custom($manager, 'Retirado', ['clients.view']); $role->update(['estado' => 'INACTIVO']);
        $this->signIn($manager)->put('/user-roles/'.$user->getKey(), ['roles' => [$role->getKey()]])->assertSessionHasErrors('roles.0');
        $this->put('/user-roles/'.$user->getKey(), ['roles' => []])->assertSessionHasErrors('roles');
    }

    public function test_every_authenticated_module_route_has_a_permission_mapping(): void
    {
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (! in_array('auth', $route->gatherMiddleware()) || in_array($route->getName(), ['logout', 'session.status', 'dashboard'])) continue;
            foreach ($route->methods() as $method) {
                $permission = \App\Support\AccessCatalog::forRoute($route->getName() ?? '', $method);
                $this->assertNotNull($permission, $route->uri());
                $this->assertArrayHasKey($permission, \App\Support\AccessCatalog::permissions());
            }
        }
    }

    public function test_a_role_with_view_permission_cannot_edit_roles_or_assign_accounts(): void
    {
        $manager = $this->manager();
        $role = $this->custom($manager, 'Auditor de accesos', ['roles.view', 'user-roles.view']);
        $user = User::factory()->create(['rol_id' => $role->getKey()]);
        $this->signIn($user)->get('/roles?role='.$role->getKey())->assertOk();
        $this->get('/user-roles?user='.$user->getKey())->assertOk();
        $this->put('/roles/'.$role->getKey(), ['nombre' => 'Cambio'])->assertForbidden();
        $this->put('/user-roles/'.$user->getKey(), ['roles' => [$manager->rol_id]])->assertForbidden();
    }
}
