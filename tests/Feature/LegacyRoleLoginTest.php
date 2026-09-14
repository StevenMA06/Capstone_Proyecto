<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRoleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_gerencia_account_can_login_without_role_migration(): void
    {
        config(['access.two_factor_enabled' => false]);
        $role = Role::create(['nombre' => ' Gerencia ', 'estado' => 'ACTIVO']);
        $user = User::factory()->create(['rol_id' => $role->getKey()]);
        $hash = $user->password_hash;
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertOk();
        $this->get('/users')->assertOk();
        $this->get('/access-history')->assertOk();
        $this->assertSame($hash, $user->fresh()->password_hash);
        $this->assertTrue($user->fresh()->isManager());
        $this->assertFalse($user->fresh()->isAdministrator());
        $this->assertDatabaseHas('roles', ['rol_id' => $role->getKey(), 'nombre' => ' Gerencia ']);
        $this->put('/users/'.$user->getKey(), ['nombre_usuario' => $user->nombre_usuario, 'correo' => $user->correo, 'rol_id' => $role->getKey()])->assertSessionHasNoErrors();
        $this->patch('/users/'.$user->getKey().'/status', ['is_active' => false])->assertSessionHasErrors('rol_id');
    }

    public function test_legacy_office_login_retains_only_office_permissions(): void
    {
        config(['access.two_factor_enabled' => false]);
        $role = Role::create(['nombre' => 'Personal autorizado', 'estado' => 'ACTIVO']);
        $user = User::factory()->create(['rol_id' => $role->getKey()]);
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertRedirect('/dashboard');
        $this->get('/clients')->assertOk();
        $this->get('/contracts')->assertOk();
        $this->get('/users')->assertForbidden();
        $this->get('/equipment')->assertForbidden();
    }

    public function test_aliases_do_not_bypass_password_or_inactive_role_checks(): void
    {
        config(['access.two_factor_enabled' => false]);
        $role = Role::create(['nombre' => 'Gerencia', 'estado' => 'ACTIVO']);
        $user = User::factory()->create(['rol_id' => $role->getKey()]);
        $this->post('/login', ['email' => $user->correo, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $role->update(['estado' => 'INACTIVO']);
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
