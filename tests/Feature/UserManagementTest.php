<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Repositories\AccessRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::firstOrCreate(['nombre' => Role::MANAGER], ['estado' => 'ACTIVO']);

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    private function data(array $overrides = []): array
    {
        $role = Role::firstOrCreate(['nombre' => Role::OFFICE], ['estado' => 'ACTIVO']);

        return array_merge(['nombre_usuario' => 'ana.perez', 'correo' => 'ana@example.com', 'rol_id' => $role->getKey(), 'empleado_id' => null,
            'password' => 'ClaveSegura12345', 'password_confirmation' => 'ClaveSegura12345'], $overrides);
    }

    public function test_personal_cannot_use_management_endpoints(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $this->get('/users')->assertRedirect('/login');
        $this->signIn($user);
        foreach (['/users', '/users/create', '/users/'.$target->getKey(), '/users/'.$target->getKey().'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/users', $this->data())->assertForbidden();
        $this->put('/users/'.$target->getKey(), $this->data())->assertForbidden();
        $this->patch('/users/'.$target->getKey().'/status', ['is_active' => false])->assertForbidden();
    }

    public function test_rf01_manager_registers_an_account_in_usuarios(): void
    {
        $this->signIn($this->manager())->post('/users', $this->data(['nombre_usuario' => ' ANA.PEREZ ', 'correo' => ' ANA@EXAMPLE.COM ']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('nombre_usuario', 'ana.perez')->firstOrFail();
        $this->assertSame('ana@example.com', $user->correo);
        $this->assertSame('ACTIVO', $user->estado);
        $this->assertTrue(Hash::check('ClaveSegura12345', $user->password_hash));
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
    }

    public function test_required_duplicate_and_invalid_fields_are_rejected(): void
    {
        $this->signIn($this->manager())->post('/users', [])->assertSessionHasErrors(['nombre_usuario', 'correo', 'rol_id', 'password']);
        User::factory()->create(['nombre_usuario' => 'ana.perez', 'correo' => 'ana@example.com']);
        $this->post('/users', $this->data(['rol_id' => 99999, 'password' => '123', 'password_confirmation' => 'otra']))
            ->assertSessionHasErrors(['nombre_usuario', 'correo', 'rol_id', 'password']);
        $this->assertDatabaseCount('usuarios', 2);
    }

    public function test_rf02_lists_accounts_and_renders_forms(): void
    {
        $target = User::factory()->create(['nombre_usuario' => 'personal.uno']);
        $this->signIn($this->manager())->get('/users')->assertOk()->assertSee('personal.uno');
        $this->get('/users/create')->assertOk()->assertSee('Registrar cuenta');
        $this->get('/users/'.$target->getKey().'/edit')->assertOk()->assertSee('Editar cuenta');
    }

    public function test_rf03_updates_fields_and_preserves_blank_password(): void
    {
        $target = User::factory()->create();
        $hash = $target->password_hash;
        $this->signIn($this->manager())->put('/users/'.$target->getKey(), $this->data(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('ana.perez', $target->fresh()->nombre_usuario);
        $this->assertSame($hash, $target->fresh()->password_hash);
    }

    public function test_password_changes_revoke_sessions_and_ignore_mass_assignment(): void
    {
        $target = User::factory()->create();
        $session = app(AccessRepository::class)->begin($target, '127.0.0.1', 'test');
        $this->signIn($this->manager())->put('/users/'.$target->getKey(), $this->data(['estado' => 'BLOQUEADO', 'usuario_id' => 9999]))->assertSessionHasNoErrors();
        $this->assertSame('ACTIVO', $target->fresh()->estado);
        $this->assertTrue(Hash::check('ClaveSegura12345', $target->fresh()->password_hash));
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $session, 'estado' => 'CERRADA']);
    }

    public function test_rf04_assigns_any_active_database_role(): void
    {
        $role = Role::firstOrCreate(['nombre' => Role::TECHNICIAN, 'estado' => 'ACTIVO']);
        $target = User::factory()->create();
        $session = app(AccessRepository::class)->begin($target, '127.0.0.1', 'test');
        $this->signIn($this->manager())->put('/users/'.$target->getKey(), $this->data(['rol_id' => $role->getKey(), 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $this->assertSame(Role::TECHNICIAN, $target->fresh()->role->nombre);
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $session, 'estado' => 'CERRADA']);
        $role->update(['estado' => 'INACTIVO']);
        $this->put('/users/'.$target->getKey(), $this->data(['rol_id' => $role->getKey()]))->assertSessionHasErrors('rol_id');
    }

    public function test_rf05_and_rf06_enable_disable_and_unlock_accounts(): void
    {
        $target = User::factory()->create(['estado' => 'BLOQUEADO']);
        $this->signIn($this->manager())->patch('/users/'.$target->getKey().'/status', ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame('ACTIVO', $target->fresh()->estado);
        $session = app(AccessRepository::class)->begin($target, '127.0.0.1', 'test');
        $this->patch('/users/'.$target->getKey().'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $target->fresh()->estado);
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $session, 'estado' => 'CERRADA']);
        $this->patch('/users/'.$target->getKey().'/status', ['is_active' => 'otro'])->assertSessionHasErrors('is_active');
    }

    public function test_rf07_and_rf08_combine_search_and_role_filter(): void
    {
        $person = User::factory()->create(['nombre_usuario' => 'ana.personal']);
        $manager = $this->manager();
        User::factory()->create(['nombre_usuario' => 'ana.gerencia', 'rol_id' => $manager->rol_id]);
        User::factory()->create(['nombre_usuario' => 'otro.personal']);
        $this->signIn($manager)->get('/users?search=ana&role='.$person->rol_id)->assertOk()
            ->assertSee('ana.personal')->assertDontSee('ana.gerencia')->assertDontSee('otro.personal');
        $this->get('/users?search=inexistente')->assertOk()->assertSee('No se encontraron cuentas');
    }

    public function test_search_underscore_is_literal_and_pagination_preserves_filters(): void
    {
        User::factory()->create(['nombre_usuario' => 'ana_uno']);
        User::factory()->create(['nombre_usuario' => 'anaXuno']);
        $this->signIn($this->manager())->get('/users?search=ana_uno')->assertOk()->assertSee('ana_uno')->assertDontSee('anaXuno');
        User::factory()->count(17)->sequence(fn ($s) => ['nombre_usuario' => 'equipo'.$s->index])->create();
        $this->get('/users?search=equipo')->assertOk()->assertSee('Página 1 de 2')->assertSee('search=equipo', false);
    }

    public function test_rf09_detail_shows_sql_identifier_role_and_actual_state(): void
    {
        $target = User::factory()->create(['estado' => 'BLOQUEADO']);
        $this->signIn($this->manager())->get('/users/'.$target->getKey())->assertOk()
            ->assertSee('#'.$target->getKey())->assertSee($target->nombre_usuario)->assertSee(Role::OFFICE)->assertSee('Bloqueado')
            ->assertDontSee($target->password_hash);
        $this->get('/users/999999')->assertNotFound();
    }

    public function test_employee_link_is_optional_unique_and_must_be_active(): void
    {
        $employee = Employee::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'cargo' => 'Analista', 'estado' => 'ACTIVO']);
        $this->signIn($this->manager())->post('/users', $this->data(['empleado_id' => $employee->getKey()]))->assertSessionHasNoErrors();
        $this->assertSame('Ana Pérez', User::where('nombre_usuario', 'ana.perez')->firstOrFail()->name);
        $this->post('/users', $this->data(['nombre_usuario' => 'otra', 'correo' => 'otra@example.com', 'empleado_id' => $employee->getKey()]))->assertSessionHasErrors('empleado_id');
        $employee->update(['estado' => 'INACTIVO']);
        $this->post('/users', $this->data(['nombre_usuario' => 'otra', 'correo' => 'otra@example.com', 'empleado_id' => $employee->getKey()]))->assertSessionHasErrors('empleado_id');
    }

    public function test_last_active_management_account_is_protected(): void
    {
        $manager = $this->manager();
        $this->signIn($manager)->patch('/users/'.$manager->getKey().'/status', ['is_active' => false])->assertSessionHasErrors('rol_id');
        $this->put('/users/'.$manager->getKey(), $this->data())->assertSessionHasErrors('rol_id');
        $this->assertTrue($manager->fresh()->isManager());
    }

    public function test_seeding_is_idempotent_and_cli_creates_first_manager(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('roles', count(Role::NAMES));
        $this->assertDatabaseCount('usuarios', 0);
        $this->artisan('app:create-user', ['--role' => Role::MANAGER, '--username' => 'gerencia'])
            ->expectsQuestion('Correo electrónico', 'gerencia@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, letras y números)', 'MiClaveSegura123')
            ->expectsQuestion('Confirma la contraseña', 'MiClaveSegura123')->assertSuccessful();
        $this->assertTrue(User::where('nombre_usuario', 'gerencia')->firstOrFail()->isManager());
    }
}
