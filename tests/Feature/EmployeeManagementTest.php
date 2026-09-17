<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Repositories\AccessRepository;
use App\Repositories\UserRepository;
use App\Services\EmployeeService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('America/Lima')->setDate(2026, 9, 16)->setTime(12, 0));
    }

    private function actor(string $role = Role::MANAGER): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', $role)->value('rol_id')]);
    }

    private function data(array $changes = []): array
    {
        return array_merge(['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'cargo' => 'Técnico de Campo', 'correo' => 'ana@example.test', 'telefono' => '987654321', 'fecha_ingreso' => '2026-09-01', 'fecha_cese' => '2026-09-30'], $changes);
    }

    public function test_managers_register_consult_search_and_edit_without_deleting_history(): void
    {
        $this->signIn($this->actor())->get('/employees/create')->assertOk()->assertSee('Fecha de inicio')->assertSee('Fecha de cese');
        $this->post('/employees', $this->data(['estado' => 'INACTIVO']))->assertSessionHasNoErrors()->assertRedirect();
        $employee = Employee::firstOrFail();
        $this->assertSame('ACTIVO', $employee->estado);
        $this->get('/employees?search=12345678')->assertOk()->assertSee('Ana')->assertSee('2026-09-30');
        $this->get('/employees?search=Pérez')->assertSee('Ana');
        $this->get('/employees?search=NO-EXISTE')->assertSee('No se encontraron empleados');
        $this->get('/employees/'.$employee->getKey())->assertOk()->assertSee('Técnico de Campo');
        $this->put('/employees/'.$employee->getKey(), $this->data(['nombres' => '<script>prueba</script>']))->assertSessionHasNoErrors();
        $this->get('/employees/'.$employee->getKey())->assertSee('&lt;script&gt;prueba&lt;/script&gt;', false)->assertDontSee('<script>prueba</script>', false);
        $this->delete('/employees/'.$employee->getKey())->assertStatus(405);
        $this->assertDatabaseCount('empleados', 1);
    }

    public function test_required_fields_dates_dni_and_duplicates_are_validated(): void
    {
        $this->signIn($this->actor())->post('/employees', [])->assertSessionHasErrors(['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'cargo', 'fecha_ingreso', 'fecha_cese']);
        $this->post('/employees', $this->data(['numero_documento' => '123', 'fecha_cese' => '2026-08-01']))->assertSessionHasErrors(['numero_documento', 'fecha_cese']);
        $this->post('/employees', $this->data(['fecha_ingreso' => '2026-02-30']))->assertSessionHasErrors('fecha_ingreso');
        $this->post('/employees', $this->data(['nombres' => '   ']))->assertSessionHasErrors('nombres');
        Employee::create($this->data());
        $this->post('/employees', $this->data())->assertSessionHasErrors(['numero_documento', 'correo']);
        $this->assertDatabaseCount('empleados', 1);
    }

    public function test_position_is_selected_from_the_two_allowed_options(): void
    {
        $this->signIn($this->actor())->get('/employees/create')->assertOk()
            ->assertSee('<select id="cargo"', false)->assertSee('Técnico de Campo')->assertSee('Asistente de ventas');
        $this->post('/employees', $this->data(['cargo' => 'Gerente']))->assertSessionHasErrors('cargo');
        $this->post('/employees', $this->data(['cargo' => 'Asistente de ventas']))->assertSessionHasNoErrors();
        $employee = Employee::firstOrFail();
        $this->assertSame('Asistente de ventas', $employee->cargo);
        $this->put('/employees/'.$employee->getKey(), $this->data(['cargo' => 'Otro']))->assertSessionHasErrors('cargo');
        $this->assertSame('Asistente de ventas', $employee->fresh()->cargo);
    }

    public function test_expired_creation_is_inactive_and_office_and_technician_cannot_manage(): void
    {
        $this->get('/employees')->assertRedirect('/login');
        $this->signIn($this->actor(Role::ADMIN))->post('/employees', $this->data(['fecha_cese' => '2026-09-15']))->assertSessionHasNoErrors();
        $employee = Employee::firstOrFail();
        $this->assertSame('INACTIVO', $employee->estado);
        foreach ([Role::OFFICE, Role::TECHNICIAN] as $role) {
            $this->signIn($this->actor($role))->get('/employees')->assertForbidden();
            $this->get('/employees/create')->assertForbidden();
            $this->get('/employees/'.$employee->getKey())->assertForbidden();
            $this->post('/employees', $this->data())->assertForbidden();
            $this->put('/employees/'.$employee->getKey(), $this->data())->assertForbidden();
            $this->patch('/employees/'.$employee->getKey().'/status', ['is_active' => true])->assertForbidden();
        }
    }

    public function test_expiry_uses_end_of_day_in_peru_revokes_access_and_preserves_shared_role(): void
    {
        $employee = Employee::create($this->data(['fecha_cese' => '2026-09-16']));
        $user = $this->actor(Role::TECHNICIAN);
        $user->update(['empleado_id' => $employee->getKey()]);
        $other = $this->actor(Role::TECHNICIAN);
        $session = app(AccessRepository::class)->begin($user, '127.0.0.1', 'test');
        $this->travelTo(Carbon::parse('2026-09-17 04:59:59', 'UTC'));
        $this->artisan('employees:expire')->assertSuccessful();
        $this->assertSame('ACTIVO', $employee->fresh()->estado);
        $this->assertTrue($user->fresh()->canAccess());
        $this->travelTo(Carbon::parse('2026-09-17 05:00:00', 'UTC'));
        $this->assertFalse($user->fresh()->canAccess());
        $this->artisan('employees:expire')->assertSuccessful();
        $this->assertSame('INACTIVO', $employee->fresh()->estado);
        $this->assertSame('INACTIVO', $user->fresh()->estado);
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $session, 'estado' => 'CERRADA']);
        $this->assertSame('ACTIVO', $other->fresh()->estado);
        $this->assertSame('ACTIVO', $other->role->estado);
        $this->assertSame('2026-09-16', $employee->fresh()->fecha_cese);
        $this->assertSame(0, app(EmployeeService::class)->expire());
    }

    public function test_request_fallback_blocks_existing_session_and_new_login_without_scheduler(): void
    {
        $employee = Employee::create($this->data(['fecha_cese' => '2026-09-16']));
        $user = $this->actor(Role::OFFICE);
        $user->update(['empleado_id' => $employee->getKey()]);
        $this->signIn($user);
        $this->travelTo(Carbon::parse('2026-09-17 00:00:00', 'America/Lima'));
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame('INACTIVO', $user->fresh()->estado);
        config(['access.two_factor_enabled' => false]);
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_expired_and_future_employees_are_not_eligible_and_forged_assignments_fail(): void
    {
        $expired = Employee::create($this->data(['fecha_cese' => '2026-09-15']));
        $future = Employee::create($this->data(['numero_documento' => '12345679', 'correo' => null, 'fecha_ingreso' => '2026-09-20']));
        $eligible = Employee::create($this->data(['numero_documento' => '12345670', 'correo' => null]));
        $this->assertSame([$eligible->getKey()], app(UserRepository::class)->employees()->modelKeys());
        $this->signIn($this->actor())->get('/users/create')->assertOk()->assertViewHas('employees', fn ($employees) => $employees->modelKeys() === [$eligible->getKey()]);
        $data = ['nombre_usuario' => 'nuevo', 'correo' => 'nuevo@example.test', 'rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id'), 'empleado_id' => $expired->getKey(), 'password' => 'ClaveSegura12345', 'password_confirmation' => 'ClaveSegura12345'];
        $this->post('/users', $data)->assertSessionHasErrors('empleado_id');
        $data['empleado_id'] = $future->getKey();
        $this->post('/users', $data)->assertSessionHasErrors('empleado_id');
        try {
            app(UserService::class)->create($data);
            $this->fail('The service must recheck eligibility under lock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('empleado_id', $e->errors());
        }
        $this->assertDatabaseMissing('usuarios', ['nombre_usuario' => 'nuevo']);
    }

    public function test_editing_end_date_disables_accounts_and_renewal_requires_explicit_activation(): void
    {
        $employee = Employee::create($this->data());
        $user = $this->actor(Role::OFFICE);
        $user->update(['empleado_id' => $employee->getKey()]);
        $this->signIn($this->actor())->put('/employees/'.$employee->getKey(), $this->data(['fecha_cese' => '2026-09-15']))->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $user->fresh()->estado);
        $this->patch('/users/'.$user->getKey().'/status', ['is_active' => true])->assertSessionHasErrors('empleado_id');
        $this->get('/users/'.$user->getKey().'/edit')->assertOk()->assertSee('Se conserva la asociación histórica');
        $this->put('/users/'.$user->getKey(), ['nombre_usuario' => $user->nombre_usuario, 'correo' => $user->correo, 'rol_id' => $user->rol_id, 'empleado_id' => $employee->getKey()])->assertSessionHasNoErrors();
        $this->assertSame($employee->getKey(), $user->fresh()->empleado_id);
        $this->assertSame('INACTIVO', $user->fresh()->estado);
        $this->patch('/employees/'.$employee->getKey().'/status', ['is_active' => true])->assertSessionHasErrors('fecha_cese');
        $this->put('/employees/'.$employee->getKey(), $this->data())->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $employee->fresh()->estado);
        $this->patch('/employees/'.$employee->getKey().'/status', ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $user->fresh()->estado);
        $this->patch('/users/'.$user->getKey().'/status', ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame('ACTIVO', $user->fresh()->estado);
        $this->patch('/employees/'.$employee->getKey().'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $user->fresh()->estado);
    }

    public function test_manager_cannot_change_employee_of_an_administrator(): void
    {
        $employee = Employee::create($this->data());
        $admin = $this->actor(Role::ADMIN);
        $admin->update(['empleado_id' => $employee->getKey()]);
        $this->signIn($this->actor())->put('/employees/'.$employee->getKey(), $this->data(['fecha_cese' => '2026-09-15']))->assertForbidden();
        $this->patch('/employees/'.$employee->getKey().'/status', ['is_active' => false])->assertForbidden();
        $this->assertSame('ACTIVO', $admin->fresh()->estado);
    }
}
