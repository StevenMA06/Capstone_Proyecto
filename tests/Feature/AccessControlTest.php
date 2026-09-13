<?php

namespace Tests\Feature;

use App\Mail\AccessCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->freezeTime();
    }

    private function challenge(?User $user = null): string
    {
        $user ??= User::factory()->create();
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])
            ->assertRedirect('/access/code')->assertSessionHasNoErrors();

        return Mail::sent(AccessCode::class)->last()->code;
    }

    public function test_credentials_only_create_a_hashed_challenge_and_send_to_registered_email(): void
    {
        $user = User::factory()->create();
        $code = $this->challenge($user);
        $this->assertGuest();
        $this->assertDatabaseCount('sesiones_usuario', 0);
        $this->assertDatabaseCount('historial_accesos', 0);
        $record = DB::table('codigos_acceso_2fa')->first();
        $this->assertTrue(Hash::check($code, $record->codigo_hash));
        $this->assertSame($record->codigo_2fa_id, session('pending_access.id'));
        Mail::assertSent(AccessCode::class, fn ($mail) => $mail->hasTo($user->correo));
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/access/code')->assertOk()->assertSee('one-time-code');
    }

    public function test_code_is_used_once_and_success_has_user_date_and_time(): void
    {
        $user = User::factory()->create();
        $code = $this->challenge($user);
        $pending = session('pending_access');
        $this->post('/access/code', ['code' => $code])->assertRedirect('/dashboard')->assertSessionMissing('pending_access');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('codigos_acceso_2fa', ['codigo_2fa_id' => $pending['id'], 'estado' => 'USADO']);
        $this->assertDatabaseHas('historial_accesos', ['usuario_id' => $user->getKey(), 'fecha_hora' => now()->format('Y-m-d H:i:s'), 'resultado' => 'EXITOSO']);
        $this->post('/logout');
        $this->withSession(['pending_access' => $pending])->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseCount('historial_accesos', 1);
    }

    public function test_wrong_codes_are_audited_and_five_failures_invalidate_the_code(): void
    {
        $code = $this->challenge();
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->from('/access/code')->post('/access/code', ['code' => $wrong])
                ->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
            $this->assertGuest();
        }
        $this->assertDatabaseHas('codigos_acceso_2fa', ['intentos' => 5, 'estado' => 'INVALIDADO']);
        $this->assertDatabaseCount('historial_accesos', 5);
        $this->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_expiry_is_inclusive_and_resend_replaces_expired_code(): void
    {
        $code = $this->challenge();
        $old = session('pending_access.id');
        $this->travel(5)->minutes();
        $this->post('/access/code', ['code' => $code])->assertSessionHasErrors(['code' => 'El código ha vencido. Solicita uno nuevo.']);
        $this->assertDatabaseHas('historial_accesos', ['resultado' => '2FA_VENCIDO']);
        $this->post('/access/code/resend')->assertRedirect('/access/code');
        $new = Mail::sent(AccessCode::class)->last()->code;
        $this->assertNotSame($code, $new);
        $this->assertNotSame($old, session('pending_access.id'));
        $this->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->post('/access/code', ['code' => $new])->assertRedirect('/dashboard');
    }

    public function test_resend_cooldown_and_previous_live_code_invalidation(): void
    {
        $code = $this->challenge();
        $old = session('pending_access.id');
        $this->post('/access/code/resend')->assertSessionHasErrors('code');
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/access/code/resend')->assertRedirect('/access/code');
        $this->assertDatabaseHas('codigos_acceso_2fa', ['codigo_2fa_id' => $old, 'estado' => 'INVALIDADO']);
        $this->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_codes_cannot_be_submitted_without_the_original_challenge(): void
    {
        $code = $this->challenge();
        session()->forget('pending_access');
        $this->post('/access/code', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_account_changes_block_pending_access(): void
    {
        $user = User::factory()->create();
        $code = $this->challenge($user);
        $user->update(['correo' => 'changed@example.com']);
        $this->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseCount('sesiones_usuario', 0);
    }

    public function test_disabled_account_cannot_complete_second_factor(): void
    {
        $user = User::factory()->create();
        $code = $this->challenge($user);
        $user->update(['estado' => 'INACTIVO']);
        $this->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_cancelling_invalidates_code_and_clears_challenge(): void
    {
        $this->challenge();
        $this->post('/access/code/cancel')->assertRedirect('/login')->assertSessionMissing('pending_access');
        $this->assertDatabaseHas('codigos_acceso_2fa', ['estado' => 'INVALIDADO']);
    }

    public function test_mail_failure_does_not_create_an_authenticated_session(): void
    {
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('codigos_acceso_2fa', ['estado' => 'INVALIDADO']);
        $this->assertDatabaseCount('sesiones_usuario', 0);
    }

    public function test_session_status_does_not_extend_inactivity_and_expires_session(): void
    {
        $this->signIn(User::factory()->create());
        $id = session('login_session_id');
        $expires = DB::table('sesiones_usuario')->where('sesion_id', $id)->value('expira_en');
        $this->travel(1)->minutes();
        $this->getJson('/session/status')->assertOk();
        $this->assertSame($expires, DB::table('sesiones_usuario')->where('sesion_id', $id)->value('expira_en'));
        $this->travel(config('session.lifetime'))->minutes();
        $this->getJson('/session/status')->assertUnauthorized();
        $this->assertGuest();
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $id, 'estado' => 'EXPIRADA']);
    }

    public function test_failed_resend_returns_to_login_instead_of_leaving_an_unusable_challenge(): void
    {
        $this->challenge();
        $this->travel(61)->seconds();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post('/access/code/resend')->assertRedirect('/login')->assertSessionHasErrors('code')->assertSessionMissing('pending_access');
        $this->assertSame(0, DB::table('codigos_acceso_2fa')->where('estado', 'VIGENTE')->count());
        $this->assertGuest();
    }

    public function test_per_user_email_limit_survives_new_login_requests(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->challenge($user);
            $this->travel(61)->seconds();
        }
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('code');
        Mail::assertSentCount(5);
        $this->assertGuest();
    }

    public function test_challenge_expires_and_cannot_be_extended_by_resending(): void
    {
        $this->challenge();
        $until = session('pending_access.until');
        $this->travel(61)->seconds();
        $this->post('/access/code/resend')->assertRedirect('/access/code');
        $this->assertSame($until, session('pending_access.until'));
        $this->travel(15)->minutes();
        $this->get('/access/code')->assertRedirect('/login')->assertSessionMissing('pending_access');
    }

    public function test_invalid_code_formats_and_csrf_cannot_bypass_verification(): void
    {
        $this->challenge();
        foreach (['', '12345', '1234567', 'abcdef', ['123456']] as $code) {
            $this->post('/access/code', ['code' => $code])->assertSessionHasErrors('code');
            $this->assertGuest();
        }
        $this->app['env'] = 'local';
        foreach (['/access/code', '/access/code/resend', '/access/code/cancel'] as $route) {
            $this->post($route, ['code' => '123456'])->assertStatus(419);
        }
    }

    public function test_authenticated_activity_extends_the_server_deadline(): void
    {
        $this->signIn(User::factory()->create());
        $id = session('login_session_id');
        $this->travel(10)->minutes();
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => $id, 'expira_en' => now()->addMinutes(config('session.lifetime'))->format('Y-m-d H:i:s')]);
    }

    public function test_history_requires_management_role_and_filters_combined(): void
    {
        $staff = User::factory()->create();
        $this->signIn($staff)->get('/access-history')->assertForbidden();
        $role = Role::firstOrCreate(['nombre' => 'Gerencia'], ['estado' => 'ACTIVO']);
        $manager = User::factory()->create(['rol_id' => $role->getKey()]);
        DB::table('historial_accesos')->insert([
            ['usuario_id' => $staff->getKey(), 'nombre_usuario_intentado' => 'buscado', 'fecha_hora' => '2026-09-12 10:20:30', 'resultado' => '2FA_INCORRECTO'],
            ['usuario_id' => null, 'nombre_usuario_intentado' => 'otro', 'fecha_hora' => '2026-09-12 11:20:30', 'resultado' => 'CREDENCIALES_INCORRECTAS'],
            ['usuario_id' => $staff->getKey(), 'nombre_usuario_intentado' => 'buscado', 'fecha_hora' => '2026-09-12 12:20:30', 'resultado' => 'EXITOSO'],
        ]);
        $this->signIn($manager)->get('/access-history?search=buscado&date=2026-09-12&result=RECHAZADO')
            ->assertOk()->assertSee('10:20:30')->assertDontSee('11:20:30')->assertDontSee('12:20:30');
        $this->get('/access-history?date=2026-09-12&result=EXITOSO')->assertOk()->assertSee('12:20:30')->assertDontSee('10:20:30');
        $this->get('/access-history?date=invalid')->assertSessionHasErrors('date');
    }
}
