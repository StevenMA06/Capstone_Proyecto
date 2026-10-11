<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DniLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.apiperu.key' => 'test-private-key']);
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', Role::ADMIN)->value('rol_id')]);
    }

    private function person(array $changes = []): array
    {
        return ['success' => true, 'data' => array_merge([
            'numero' => '01234567', 'nombres' => 'ANA MARIA',
            'apellido_paterno' => 'PRUEBA', 'apellido_materno' => 'DEMO',
            'direccion' => '',
        ], $changes)];
    }

    public function test_client_and_employee_forms_include_the_shared_lookup(): void
    {
        $this->signIn($this->admin());
        $this->get('/clients/create')->assertOk()->assertSee('data-dni-lookup', false)->assertSee('clients/dni-lookup', false);
        $this->get('/employees/create')->assertOk()->assertSee('data-dni-lookup', false)->assertSee('employees/dni-lookup', false);
        Http::assertNothingSent();
    }

    public function test_guest_cannot_consume_the_provider(): void
    {
        $this->postJson('/clients/dni-lookup', ['dni' => '01234567'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_office_cannot_lookup_employees_and_read_only_role_cannot_lookup_clients(): void
    {
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $this->signIn($office)->postJson('/employees/dni-lookup', ['dni' => '01234567'])->assertForbidden();

        $role = Role::create(['nombre' => 'Consulta de clientes', 'estado' => 'ACTIVO']);
        DB::table('rol_permiso')->insert(['rol_id' => $role->getKey(), 'permiso_id' => DB::table('permisos')->where('codigo', 'clients.view')->value('permiso_id'), 'permitido' => true]);
        $this->signIn(User::factory()->create(['rol_id' => $role->getKey()]))
            ->postJson('/clients/dni-lookup', ['dni' => '01234567'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_valid_lookup_preserves_leading_zero_and_only_returns_form_data(): void
    {
        Http::fake(['api.apiperu.pe/dni' => Http::response($this->person(['direccion_completa' => 'DIRECCION DE PRUEBA', 'telefono' => 'private']), 200)]);
        $this->signIn($this->admin());
        foreach (['clients', 'employees'] as $module) {
            $response = $this->postJson('/'.$module.'/dni-lookup', ['dni' => '01234567'])
                ->assertOk()->assertExactJson(['data' => [
                    'dni' => '01234567', 'nombres' => 'ANA MARIA', 'apellidos' => 'PRUEBA DEMO', 'direccion' => 'DIRECCION DE PRUEBA',
                ]]);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        Http::assertSent(fn ($request) => $request->url() === 'https://api.apiperu.pe/dni'
            && $request->method() === 'POST' && $request['dni'] === '01234567'
            && $request->hasHeader('Authorization', 'Bearer test-private-key'));
        $this->assertDatabaseMissing('clientes', ['numero_documento' => '01234567']);
        $this->assertDatabaseMissing('empleados', ['numero_documento' => '01234567']);
    }

    public function test_office_can_lookup_for_client_registration(): void
    {
        Http::fake(['api.apiperu.pe/dni' => Http::response($this->person(), 200)]);
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $this->signIn($office)->postJson('/clients/dni-lookup', ['dni' => '01234567'])
            ->assertOk()->assertJsonPath('data.direccion', '');
    }

    public function test_invalid_dni_is_rejected_without_spending_quota(): void
    {
        $this->signIn($this->admin());
        foreach (['', '1234567', '123456789', 'ABCDEFGH', 12345678] as $dni) {
            $this->postJson('/clients/dni-lookup', ['dni' => $dni])->assertUnprocessable()->assertJsonValidationErrors('dni');
        }
        Http::assertNothingSent();
    }

    public function test_missing_key_allows_manual_entry_without_calling_provider(): void
    {
        config(['services.apiperu.key' => '']);
        $this->signIn($this->admin())->postJson('/clients/dni-lookup', ['dni' => '01234567'])
            ->assertStatus(503)->assertJsonPath('message', 'La consulta de DNI no está configurada. Puedes completar los datos manualmente.');
        Http::assertNothingSent();
    }

    public function test_provider_failures_are_reported_in_spanish_without_leaking_details(): void
    {
        $this->signIn($this->admin());
        $statuses = [404 => 404, 401 => 503, 403 => 503, 429 => 429, 500 => 503];
        $sequence = Http::sequence();
        foreach ($statuses as $upstream => $expected) $sequence->push(['message' => 'secret upstream error'], $upstream);
        Http::fake(['api.apiperu.pe/dni' => $sequence]);
        foreach ($statuses as $upstream => $expected) {
            $this->postJson('/clients/dni-lookup', ['dni' => '01234567'])->assertStatus($expected)
                ->assertDontSee('secret upstream error')->assertSee('manualmente');
        }
    }

    public function test_unrelated_or_malformed_provider_data_is_not_used(): void
    {
        $this->signIn($this->admin());
        $payloads = [$this->person(['numero' => '99999999']), $this->person(['nombres' => []]), ['success' => true, 'data' => null]];
        $sequence = Http::sequence();
        foreach ($payloads as $payload) $sequence->push($payload);
        Http::fake(['api.apiperu.pe/dni' => $sequence]);
        foreach ($payloads as $payload) {
            $this->postJson('/clients/dni-lookup', ['dni' => '01234567'])->assertStatus(503);
        }
    }

    public function test_network_failure_does_not_expose_exception_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('sensitive diagnostic'));
        $this->signIn($this->admin())->postJson('/clients/dni-lookup', ['dni' => '01234567'])
            ->assertStatus(503)->assertDontSee('sensitive diagnostic')->assertSee('manualmente');
    }
}
