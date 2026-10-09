<?php

namespace Tests\Feature;

use App\Mail\AccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.two_factor_enabled' => true]);
        Mail::fake();
    }

    private function completeCode(): void
    {
        $code = Mail::sent(AccessCode::class)->last()->code;
        $this->post('/access/code', ['code' => $code])->assertRedirect('/dashboard');
    }

    public function test_login_form_uses_the_company_logo_and_csrf(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertSee('lunnys-peru.png')->assertSee('name="_token"', false);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_tunnel_requests_keep_public_host_for_login_redirect_and_form(): void
    {
        $headers = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost:8000',
            'HTTP_X_FORWARDED_HOST' => 'tunnel.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ];

        $this->withServerVariables($headers)->get('http://localhost:8000/technician/installations/1')
            ->assertRedirect('https://tunnel.example.test/login');
        $this->withServerVariables($headers)->get('http://localhost:8000/login')
            ->assertOk()
            ->assertSee('action="https://tunnel.example.test/login"', false)
            ->assertSee('https://tunnel.example.test/css/app.css', false);
    }

    public function test_login_by_email_normalizes_input_and_regenerates_session(): void
    {
        $user = User::factory()->create(['correo' => 'ana@example.com']);
        $this->withSession(['before_login' => true, 'url.intended' => '/clients']);
        $old = session()->getId();
        $this->post('/login', ['email' => ' ANA@EXAMPLE.COM ', 'password' => 'ClaveSegura12345'])
            ->assertRedirect('/access/code')->assertSessionHasNoErrors();
        $this->completeCode();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($old, session()->getId());
        $this->assertDatabaseHas('historial_accesos', ['usuario_id' => $user->getKey(), 'resultado' => 'EXITOSO']);
        $this->assertDatabaseHas('sesiones_usuario', ['usuario_id' => $user->getKey(), 'estado' => 'ACTIVA']);
        $this->assertNotNull($user->fresh()->ultimo_acceso_en);
        $this->get('/dashboard')->assertRedirect('/dashboard/call-center');
    }

    public function test_login_by_username_is_supported(): void
    {
        $user = User::factory()->create(['nombre_usuario' => 'ana.perez']);
        $this->post('/login', ['email' => 'ANA.PEREZ', 'password' => 'ClaveSegura12345'])->assertRedirect('/access/code');
        $this->completeCode();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_generic_and_never_flash_password(): void
    {
        User::factory()->create(['correo' => 'ana@example.com']);
        foreach (['ana@example.com', 'missing@example.com'] as $login) {
            $this->from('/login')->post('/login', ['email' => $login, 'password' => 'incorrecta'])
                ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Las credenciales no son correctas o la cuenta no está disponible.'])
                ->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
        $this->assertDatabaseCount('historial_accesos', 2);
        $this->assertDatabaseCount('sesiones_usuario', 0);
    }

    public function test_inactive_and_blocked_accounts_are_rejected(): void
    {
        foreach (['INACTIVO', 'BLOQUEADO'] as $state) {
            $user = User::factory()->create(['estado' => $state]);
            $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertDatabaseHas('historial_accesos', ['usuario_id' => $user->getKey(), 'resultado' => $state === 'BLOQUEADO' ? 'BLOQUEADO' : 'CUENTA_INACTIVA']);
        }
    }

    public function test_inactive_roles_cannot_access(): void
    {
        $user = User::factory()->create();
        $user->role->update(['estado' => 'INACTIVO']);
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_validates_required_fields_and_rejects_unsupported_remember_me(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->post('/login', ['email' => 'ana', 'password' => 'ClaveSegura12345', 'remember' => true])->assertSessionHasErrors('remember');
        $this->assertGuest();
    }

    public function test_csrf_protection_is_enforced(): void
    {
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'ana', 'password' => 'whatever'])->assertStatus(419);
    }

    public function test_five_failures_block_login_until_timeout(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->correo, 'password' => 'incorrecta']);
        }
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertRedirect('/access/code');
        $this->completeCode();
        $this->assertAuthenticated();
    }

    public function test_logout_closes_logical_session_and_invalidates_browser_session(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->withSession(['private_data' => true]);
        $id = session('login_session_id');
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_data');
        $this->assertGuest();
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $id, 'estado' => 'CERRADA']);
    }

    public function test_revoked_or_expired_sessions_cannot_continue(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        DB::table('sesiones_usuario')->where('sesion_id', session('login_session_id'))->update(['estado' => 'CERRADA']);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->signIn($user);
        DB::table('sesiones_usuario')->where('sesion_id', session('login_session_id'))->update(['expira_en' => now()->subMinute()]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_disabled_accounts_are_removed_from_existing_sessions(): void
    {
        $user = User::factory()->create(['estado' => 'INACTIVO']);
        $this->signIn($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_active_sessions_survive_multiple_requests_in_the_same_second(): void
    {
        $this->freezeTime();
        $this->signIn(User::factory()->create());
        $this->get('/dashboard')->assertRedirect('/dashboard/call-center');
        $this->get('/dashboard')->assertRedirect('/dashboard/call-center');
        $this->get('/login')->assertRedirect('/dashboard');
        $this->get('/logout')->assertStatus(405);
    }
}
