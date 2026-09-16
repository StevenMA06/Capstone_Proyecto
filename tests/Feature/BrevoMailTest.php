<?php

namespace Tests\Feature;

use App\Mail\AccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class BrevoMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'test-only-key', 'mail.from.address' => 'sender@example.test', 'mail.from.name' => 'Lunnys Perú', 'access.two_factor_enabled' => true]);
        Http::preventStrayRequests();
    }

    public function test_transport_sends_expected_payload_and_cli_supports_plain_text(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<test@brevo>'], 201)]);
        Mail::to('recipient@example.test')->send(new AccessCode('123456', 5));
        Http::assertSent(fn ($r) => $r->url() === 'https://api.brevo.com/v3/smtp/email'
            && $r->hasHeader('api-key', 'test-only-key') && $r['sender']['email'] === 'sender@example.test'
            && $r['to'][0]['email'] === 'recipient@example.test' && str_contains($r['htmlContent'], '123456')
            && str_contains($r['subject'], 'código'));
        $this->artisan('app:mail-test', ['recipient' => 'recipient@example.test'])->assertSuccessful();
        Http::assertSent(fn ($r) => isset($r['textContent']) && str_contains($r['textContent'], 'Prueba de envío'));
        Http::assertSentCount(2);
    }

    public function test_invalid_configuration_and_recipient_do_not_send(): void
    {
        Http::fake();
        $this->artisan('app:mail-test', ['recipient' => 'bad-address'])->assertFailed();
        config(['services.brevo.key' => '']);
        $this->artisan('app:mail-test', ['recipient' => 'recipient@example.test'])->expectsOutputToContain('BREVO_API_KEY')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_provider_errors_and_connection_failures_are_safe_and_not_retried(): void
    {
        foreach ([400, 401, 403, 429, 500, 302, 201] as $status) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['api.brevo.com/*' => Http::response(['message' => 'secret-body-123456'], $status)]);
            try {
                Mail::raw('123456', fn ($m) => $m->to('recipient@example.test')->subject('Test'));
                $this->fail('An unconfirmed email must fail.');
            } catch (TransportException $error) {
                $this->assertStringNotContainsString('secret-body', $error->getMessage());
                $this->assertStringNotContainsString('test-only-key', $error->getMessage());
                $this->assertStringContainsString('HTTP '.$status, $error->getMessage());
            }
            Http::assertSentCount(1);
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('secret-body'));
        $this->artisan('app:mail-test', ['recipient' => 'recipient@example.test'])->expectsOutputToContain('HTTPS')->assertFailed();
    }

    public function test_login_code_is_sent_via_api_and_required_before_access(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<login@brevo>'], 201)]);
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertRedirect('/access/code');
        $this->assertGuest();
        $record = DB::table('codigos_acceso_2fa')->first();
        $request = Http::recorded()[0][0];
        $this->assertSame($user->correo, $request['to'][0]['email']);
        preg_match('/\b[0-9]{6}\b/', $request['htmlContent'], $matches);
        $this->assertTrue(Hash::check($matches[0], $record->codigo_hash));
        $this->post('/access/code', ['code' => $matches[0]])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_api_rejection_invalidates_code_and_keeps_login_blocked(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'secret-response'], 401)]);
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->correo, 'password' => 'ClaveSegura12345'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('codigos_acceso_2fa', ['usuario_id' => $user->getKey(), 'estado' => 'INVALIDADO']);
        $this->assertDatabaseCount('sesiones_usuario', 0);
    }
}
