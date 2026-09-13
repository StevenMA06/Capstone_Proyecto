<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordOnlyLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.two_factor_enabled' => false]);
        Mail::fake();
    }

    public function test_login_without_smtp_creates_session_and_audit_then_logout_works(): void
    {
        $user = User::factory()->create();
        $this->withSession(['before' => true]);
        $old = session()->getId();
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])
            ->assertRedirect('/dashboard')->assertSessionMissing('pending_access')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($old, session()->getId());
        Mail::assertNothingSent();
        $this->assertDatabaseCount('codigos_acceso_2fa', 0);
        $this->assertDatabaseHas('historial_accesos', ['usuario_id' => $user->getKey(), 'resultado' => 'EXITOSO']);
        $this->assertDatabaseHas('sesiones_usuario', ['sesion_id' => session('login_session_id'), 'estado' => 'ACTIVA']);
        $this->get('/dashboard')->assertOk();
        $this->get('/access-history')->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_and_account_checks_cannot_be_skipped(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->correo, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->update(['estado' => 'INACTIVO']);
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('sesiones_usuario', 0);
        Mail::assertNothingSent();
    }

    public function test_old_challenges_cannot_be_used_when_second_factor_is_disabled(): void
    {
        $this->withSession(['pending_access' => ['id' => 0, 'user_id' => 0, 'until' => now()->addMinutes(5)->timestamp]])
            ->post('/access/code', ['code' => '123456'])->assertRedirect('/login')->assertSessionMissing('pending_access');
        $this->assertGuest();
        $this->post('/access/code/resend')->assertRedirect('/login');
        Mail::assertNothingSent();
    }
}
