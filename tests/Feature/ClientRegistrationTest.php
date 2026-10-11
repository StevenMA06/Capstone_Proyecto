<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $name): User
    {
        $role = Role::firstOrCreate(['nombre' => $name], ['estado' => 'ACTIVO']);

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'tipo_documento' => 'DNI',
            'numero_documento' => '00123456',
            'nombres_razon_social' => 'Ana María',
            'apellidos' => 'Pérez Gómez', 'telefono' => '900111222',
            'correo' => 'ana@example.test',
            'direccion' => 'Av. España 123',
            'ip_router' => '192.168.1.1', 'modelo_router' => 'Router de prueba',
            'usuario_router' => 'admin', 'contrasena_router' => 'Router12345',
            'ssid' => 'Red de prueba', 'contrasena_wifi' => 'Wifi12345',
        ], $overrides);
    }

    public function test_manager_registers_client_with_only_required_information(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $response = $this->signIn($creator)->post('/clients', $this->data())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', array_diff_key($this->data(), array_flip(['contrasena_router', 'contrasena_wifi'])) + [
            'creado_por' => $creator->getKey(), 'estado' => 'ACTIVO',
            'latitud' => null, 'longitud' => null,
        ]);
        $client = Client::firstOrFail();
        $response->assertRedirect('/clients/'.$client->getKey());
        $this->assertNotNull($client->creado_en);
        $this->assertNotNull($client->actualizado_en);
        $this->get('/clients/'.$client->getKey())->assertOk()
            ->assertSee('Cliente #'.$client->getKey().' registrado correctamente.')->assertSee('data-toast', false);
        $this->get('/clients/create')->assertOk();
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_authorized_personnel_registers_all_optional_fields(): void
    {
        $data = $this->data([
            'apellidos' => 'Pérez Gómez', 'correo' => 'ana@example.com', 'telefono' => '+51 900 000 001',
            'referencia_direccion' => 'Frente al parque', 'distrito' => 'Trujillo',
            'provincia' => 'Trujillo', 'departamento' => 'La Libertad',
            'latitud' => '-8.1116000', 'longitud' => '-79.0287000',
        ]);
        $this->signIn($this->userWithRole(Role::OFFICE))->post('/clients', $data)
            ->assertRedirect('/clients/'.Client::firstOrFail()->getKey())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', array_diff_key($data, array_flip(['contrasena_router', 'contrasena_wifi'])));
    }

    public function test_all_document_types_with_required_contact_information_are_supported(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));
        foreach (['DNI', 'CE', 'RUC', 'OTRO'] as $type) {
            $number = $type === 'DNI' ? '00123456' : '00123456789';
            $data = $this->data([
                'tipo_documento' => $type, 'numero_documento' => $number,
                'nombres_razon_social' => $type === 'RUC' ? 'Empresa de prueba SAC' : 'Cliente de prueba',
                'apellidos' => 'Pérez',
            ]);
            $this->post('/clients', $data)->assertSessionHasNoErrors()->assertRedirect('/clients/'.Client::where('tipo_documento', $type)->firstOrFail()->getKey());
            $this->assertDatabaseHas('clientes', [
                'tipo_documento' => $type, 'numero_documento' => $number, 'apellidos' => 'Pérez',
            ]);
        }
        $this->assertDatabaseCount('clientes', 4);
    }

    public function test_creator_state_and_identifier_cannot_be_supplied_by_browser(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $other = $this->userWithRole(Role::OFFICE);
        $this->signIn($creator)->post('/clients', $this->data([
            'creado_por' => $other->getKey(), 'estado' => 'INACTIVO', 'cliente_id' => 999999,
            'creado_en' => '2000-01-01 00:00:00',
        ]))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $this->assertSame($creator->getKey(), $client->creado_por);
        $this->assertSame('ACTIVO', $client->estado);
        $this->assertNotEquals(999999, $client->getKey());
        $this->assertNotEquals('2000-01-01 00:00:00', $client->creado_en->format('Y-m-d H:i:s'));
    }

    public function test_surname_and_phone_cannot_be_missing_or_blank(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));
        foreach ([null, '', '   '] as $blank) {
            $this->post('/clients', $this->data(['apellidos' => $blank, 'telefono' => $blank]))
                ->assertSessionHasErrors(['apellidos', 'telefono']);
        }
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_email_is_required_when_creating_and_updating_a_client(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));
        $this->post('/clients', $this->data())->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        foreach ([null, '', '   ', 'correo-invalido'] as $invalid) {
            $this->post('/clients', $this->data(['numero_documento' => '99887766', 'correo' => $invalid]))
                ->assertSessionHasErrors('correo');
            $this->put('/clients/'.$client->getKey(), $this->data(['correo' => $invalid]))
                ->assertSessionHasErrors('correo');
        }
        $this->assertDatabaseCount('clientes', 1);
        $this->assertSame('ana@example.test', $client->fresh()->correo);
    }

    public function test_form_is_reachable_from_dashboard_for_both_roles(): void
    {
        foreach ([Role::MANAGER, Role::OFFICE] as $role) {
            $this->signIn($this->userWithRole($role))->get('/dashboard')->assertRedirect($role === Role::MANAGER ? '/dashboard/gerencia' : '/dashboard/call-center');
            $this->get('/clients')->assertOk()->assertSee('Registrar cliente');
            $this->get('/clients/create')->assertOk()->assertSee('Nombres o razón social')
                ->assertSee('name="_token"', false)->assertSee('name="direccion"', false);
        }
    }

    public function test_guests_and_unapproved_roles_cannot_register(): void
    {
        $this->get('/clients/create')->assertRedirect('/login');
        $this->post('/clients', $this->data())->assertRedirect('/login');
        $this->signIn($this->userWithRole(Role::TECHNICIAN));
        $this->get('/dashboard')->assertRedirect('/technician');
        $this->get('/technician')->assertOk()->assertDontSee('Registrar cliente');
        $this->get('/clients/create')->assertForbidden();
        $this->post('/clients', $this->data())->assertForbidden();
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_inactive_account_cannot_register(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $this->signIn($creator);
        $creator->update(['estado' => 'INACTIVO']);
        $this->post('/clients', $this->data())->assertRedirect('/login');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_input_must_fit_existing_schema_and_preserves_form_on_failure(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER))->from('/clients/create')
            ->post('/clients', $this->data(['numero_documento' => str_repeat('1', 21), 'latitud' => 91]))
            ->assertRedirect('/clients/create')->assertSessionHasErrors(['numero_documento', 'latitud'])
            ->assertSessionHasInput('nombres_razon_social', 'Ana María');
        $this->post('/clients', $this->data(['direccion' => '   ']))->assertSessionHasErrors('direccion');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_existing_unique_constraint_is_handled_without_overwriting_client(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER))->post('/clients', $this->data())->assertSessionHasNoErrors();
        $this->from('/clients/create')->post('/clients', $this->data(['nombres_razon_social' => 'Otro nombre']))
            ->assertRedirect('/clients/create')->assertSessionHasErrors('numero_documento')
            ->assertSessionHasInput('nombres_razon_social', 'Otro nombre');
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', ['nombres_razon_social' => 'Ana María']);
    }
}
