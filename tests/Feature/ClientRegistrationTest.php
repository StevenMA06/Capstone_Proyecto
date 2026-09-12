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
            'direccion' => 'Av. España 123',
        ], $overrides);
    }

    public function test_manager_registers_client_with_only_required_information(): void
    {
        $creator = $this->userWithRole('Gerencia');
        $this->signIn($creator)->post('/clients', $this->data())
            ->assertRedirect('/clients/create')->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', $this->data() + [
            'creado_por' => $creator->getKey(), 'estado' => 'ACTIVO',
            'apellidos' => null, 'correo' => null, 'telefono' => null,
            'latitud' => null, 'longitud' => null,
        ]);
        $client = Client::firstOrFail();
        $this->assertNotNull($client->creado_en);
        $this->assertNotNull($client->actualizado_en);
        $this->get('/clients/create')->assertOk()
            ->assertSee('Cliente #'.$client->getKey().' registrado correctamente.');
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
        $this->signIn($this->userWithRole('Personal autorizado'))->post('/clients', $data)
            ->assertRedirect('/clients/create')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', $data);
    }

    public function test_all_document_types_and_company_without_surname_are_supported(): void
    {
        $this->signIn($this->userWithRole('Gerencia'));
        foreach (['DNI', 'CE', 'RUC', 'OTRO'] as $type) {
            $data = $this->data([
                'tipo_documento' => $type, 'numero_documento' => '00123456789',
                'nombres_razon_social' => $type === 'RUC' ? 'Empresa de prueba SAC' : 'Cliente de prueba',
                'apellidos' => '',
            ]);
            $this->post('/clients', $data)->assertSessionHasNoErrors()->assertRedirect('/clients/create');
            $this->assertDatabaseHas('clientes', [
                'tipo_documento' => $type, 'numero_documento' => '00123456789', 'apellidos' => null,
            ]);
        }
        $this->assertDatabaseCount('clientes', 4);
    }

    public function test_creator_state_and_identifier_cannot_be_supplied_by_browser(): void
    {
        $creator = $this->userWithRole('Gerencia');
        $other = $this->userWithRole('Personal autorizado');
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

    public function test_form_is_reachable_from_dashboard_for_both_roles(): void
    {
        foreach (['Gerencia', 'Personal autorizado'] as $role) {
            $this->signIn($this->userWithRole($role))->get('/dashboard')->assertOk()->assertSee('Registrar cliente');
            $this->get('/clients/create')->assertOk()->assertSee('Nombres o razón social')
                ->assertSee('name="_token"', false)->assertSee('name="direccion"', false);
        }
    }

    public function test_guests_and_unapproved_roles_cannot_register(): void
    {
        $this->get('/clients/create')->assertRedirect('/login');
        $this->post('/clients', $this->data())->assertRedirect('/login');
        $this->signIn($this->userWithRole('Técnico de campo'));
        $this->get('/dashboard')->assertOk()->assertDontSee('Registrar cliente');
        $this->get('/clients/create')->assertForbidden();
        $this->post('/clients', $this->data())->assertForbidden();
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_inactive_account_cannot_register(): void
    {
        $creator = $this->userWithRole('Gerencia');
        $this->signIn($creator);
        $creator->update(['estado' => 'INACTIVO']);
        $this->post('/clients', $this->data())->assertRedirect('/login');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_input_must_fit_existing_schema_and_preserves_form_on_failure(): void
    {
        $this->signIn($this->userWithRole('Gerencia'))->from('/clients/create')
            ->post('/clients', $this->data(['numero_documento' => str_repeat('1', 21), 'latitud' => 91]))
            ->assertRedirect('/clients/create')->assertSessionHasErrors(['numero_documento', 'latitud'])
            ->assertSessionHasInput('nombres_razon_social', 'Ana María');
        $this->post('/clients', $this->data(['direccion' => '   ']))->assertSessionHasErrors('direccion');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_existing_unique_constraint_is_handled_without_overwriting_client(): void
    {
        $this->signIn($this->userWithRole('Gerencia'))->post('/clients', $this->data())->assertSessionHasNoErrors();
        $this->from('/clients/create')->post('/clients', $this->data(['nombres_razon_social' => 'Otro nombre']))
            ->assertRedirect('/clients/create')->assertSessionHasErrors('numero_documento')
            ->assertSessionHasInput('nombres_razon_social', 'Otro nombre');
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', ['nombres_razon_social' => 'Ana María']);
    }
}
