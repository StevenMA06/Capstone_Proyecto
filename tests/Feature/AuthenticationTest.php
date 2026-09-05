<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_is_available(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertSee('name="_token"', false);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_rejects_requests_without_csrf_protection(): void
    {
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'anything'])
            ->assertStatus(419);
        $this->assertGuest();
    }

    public function test_valid_credentials_start_a_session_and_normalize_email(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com', 'password' => 'UnaClaveSegura123']);
        $this->withSession(['before_login' => true]);
        $oldSession = session()->getId();
        $this->post('/login', ['email' => ' DEMO@EXAMPLE.COM ', 'password' => 'UnaClaveSegura123'])
            ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->get('/dashboard')->assertOk()->assertSee($user->name);
    }

    public function test_wrong_password_and_unknown_user_have_the_same_error(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);
        foreach (['demo@example.com', 'missing@example.com'] as $email) {
            $this->from('/login')->post('/login', ['email' => $email, 'password' => 'incorrecta'])
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.'])
                ->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    public function test_login_requires_valid_input(): void
    {
        $this->post('/login', ['email' => 'invalido'])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_five_failed_attempts_temporarily_block_even_correct_credentials(): void
    {
        User::factory()->create(['email' => 'demo@example.com', 'password' => 'UnaClaveSegura123']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'demo@example.com', 'password' => 'incorrecta']);
        }
        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'UnaClaveSegura123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'UnaClaveSegura123'])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_successful_login_clears_failed_attempts_and_supports_remember_me(): void
    {
        User::factory()->create(['email' => 'demo@example.com', 'password' => 'UnaClaveSegura123']);
        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'incorrecta']);
        $response = $this->post('/login', ['email' => 'demo@example.com', 'password' => 'UnaClaveSegura123', 'remember' => '1']);
        $response->assertRedirect('/dashboard')->assertCookie(auth()->guard('web')->getRecallerName());
        $this->assertSame(0, RateLimiter::attempts('login:'.hash('sha256', 'demo@example.com|127.0.0.1')));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/dashboard');
    }

    public function test_logout_invalidates_the_session(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['private_data' => 'secret']);
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_data');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_logout_requires_post(): void
    {
        $this->actingAs(User::factory()->create())->get('/logout')->assertStatus(405);
    }

    public function test_user_creation_hashes_password_and_rejects_duplicate_email(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Nombre', 'Ana')
            ->expectsQuestion('Correo electrónico', 'ana@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, letras y números)', 'MiClaveSegura123')
            ->expectsQuestion('Confirma la contraseña', 'MiClaveSegura123')
            ->assertSuccessful();
        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertNotSame('MiClaveSegura123', $user->password);
        $this->assertTrue(Hash::check('MiClaveSegura123', $user->password));
        $this->artisan('app:create-user')
            ->expectsQuestion('Nombre', 'Otra persona')
            ->expectsQuestion('Correo electrónico', 'ana@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, letras y números)', 'MiClaveSegura123')
            ->expectsQuestion('Confirma la contraseña', 'MiClaveSegura123')
            ->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}
