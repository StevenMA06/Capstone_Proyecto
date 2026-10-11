<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientRouterTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_replace([
            'tipo_documento' => 'DNI', 'numero_documento' => '00123456',
            'nombres_razon_social' => 'Ana', 'apellidos' => 'Pérez',
            'correo' => 'ana@example.test', 'telefono' => '900111222', 'direccion' => 'Av. España 123',
            'ip_router' => '192.168.1.1', 'modelo_router' => 'Router de prueba', 'usuario_router' => 'admin',
            'contrasena_router' => ' Router12345 ', 'ssid' => 'Red de prueba', 'contrasena_wifi' => ' WiFi12345 ',
        ], $overrides);
    }

    private function authenticate(): void
    {
        $role = Role::where('nombre', Role::MANAGER)->firstOrFail();
        $this->signIn(User::factory()->create(['rol_id' => $role->getKey()]));
    }

    public function test_router_fields_are_required_and_errors_and_form_are_in_spanish(): void
    {
        $this->authenticate();
        $fields = ['ip_router', 'modelo_router', 'usuario_router', 'contrasena_router', 'ssid', 'contrasena_wifi'];
        foreach ([null, '', '   '] as $blank) {
            $this->from('/clients/create')->post('/clients', $this->data(array_fill_keys($fields, $blank)))
                ->assertSessionHasErrors($fields);
        }
        $this->withCookie(session()->getName(), session()->getId());
        $this->get('/clients/create')->assertOk()->assertSee('Router y red WiFi')
            ->assertSee('El campo IP del router es obligatorio.')
            ->assertSee('data-client-secret-toggle', false);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_reference_is_optional_and_passwords_are_encrypted_hidden_and_not_flashed(): void
    {
        $this->authenticate();
        $this->from('/clients/create')->post('/clients', $this->data(['ip_router' => 'no-es-ip']))
            ->assertSessionHasErrors('ip_router');
        $this->assertArrayNotHasKey('contrasena_router', session()->getOldInput());
        $this->assertArrayNotHasKey('contrasena_wifi', session()->getOldInput());
        $this->post('/clients', $this->data())->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $this->assertNull($client->numero_referencia);
        foreach (['contrasena_router', 'contrasena_wifi'] as $key) {
            $this->assertSame($this->data()[$key], $client->{$key});
            $this->assertNotSame($this->data()[$key], DB::table('clientes')->value($key));
            $this->assertArrayNotHasKey($key, $client->toArray());
        }
        $this->get('/clients/'.$client->getKey().'/edit')->assertOk()->assertDontSee('Router12345')->assertDontSee('WiFi12345');
        $this->get('/clients/'.$client->getKey())->assertOk()->assertSee('192.168.1.1')->assertSee('Configurada');
    }

    public function test_edit_preserves_blank_passwords_and_can_replace_them(): void
    {
        $this->authenticate();
        $this->post('/clients', $this->data())->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $this->put('/clients/'.$client->getKey(), $this->data([
            'contrasena_router' => '', 'contrasena_wifi' => '', 'numero_referencia' => '900222333',
            'ip_router' => '2001:db8::1', 'modelo_router' => 'Otro modelo',
        ]))->assertSessionHasNoErrors();
        $client->refresh();
        $this->assertSame(' Router12345 ', $client->contrasena_router);
        $this->assertSame(' WiFi12345 ', $client->contrasena_wifi);
        $this->assertSame('900222333', $client->numero_referencia);
        $this->assertSame('2001:db8::1', $client->ip_router);
        $this->put('/clients/'.$client->getKey(), $this->data(['contrasena_router' => '   ', 'contrasena_wifi' => '   ']))
            ->assertSessionHasNoErrors();
        $this->assertSame(' Router12345 ', $client->fresh()->contrasena_router);
        $this->put('/clients/'.$client->getKey(), $this->data(['contrasena_router' => 'NuevaRouter', 'contrasena_wifi' => 'NuevaWiFi']))
            ->assertSessionHasNoErrors();
        $this->assertSame('NuevaRouter', $client->fresh()->contrasena_router);
        $this->assertSame('NuevaWiFi', $client->fresh()->contrasena_wifi);
    }

    public function test_legacy_clients_are_preserved_but_must_complete_router_details_when_edited(): void
    {
        $this->authenticate();
        $legacy = Client::create(array_diff_key($this->data(), array_flip([
            'ip_router', 'modelo_router', 'usuario_router', 'contrasena_router', 'ssid', 'contrasena_wifi',
        ])) + ['creado_por' => auth()->id()]);
        $this->get('/clients/'.$legacy->getKey())->assertOk()->assertSee('Sin registrar');
        $this->put('/clients/'.$legacy->getKey(), $this->data(['contrasena_router' => '', 'contrasena_wifi' => '']))
            ->assertSessionHasErrors(['contrasena_router', 'contrasena_wifi']);
        $this->assertNull($legacy->fresh()->ip_router);
        $this->put('/clients/'.$legacy->getKey(), $this->data())->assertSessionHasNoErrors();
        $this->assertSame('192.168.1.1', $legacy->fresh()->ip_router);
    }
}
