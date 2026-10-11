<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $name = Role::MANAGER): User
    {
        $role = Role::firstOrCreate(['nombre' => $name], ['estado' => 'ACTIVO']);

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'tipo_documento' => 'DNI', 'numero_documento' => '00123456',
            'nombres_razon_social' => 'Ana María', 'apellidos' => 'Pérez Gómez',
            'direccion' => 'Av. España 123', 'telefono' => '900111222',
            'correo' => 'ana@example.test',
            'ip_router' => '192.168.1.1', 'modelo_router' => 'Router de prueba',
            'usuario_router' => 'admin', 'contrasena_router' => 'Router12345',
            'ssid' => 'Red de prueba', 'contrasena_wifi' => 'Wifi12345',
        ], $overrides);
    }

    private function client(User $actor, array $overrides = []): Client
    {
        return Client::create($this->data($overrides) + ['creado_por' => $actor->getKey()]);
    }

    public function test_dni_requires_exactly_eight_ascii_digits_on_create_and_update(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->signIn($actor);
        foreach (['', null, '   ', '1234567', '123456789', 'abcdefgh', '1234A678', '1.234567', '-1234567', '1e000007', '１２３４５６７８', "12345678\n9"] as $invalid) {
            $data = $this->data(['numero_documento' => $invalid, 'nombres_razon_social' => 'No guardar']);
            $this->post('/clients', $data)->assertSessionHasErrors('numero_documento');
            $this->put('/clients/'.$client->getKey(), $data)->assertSessionHasErrors('numero_documento');
            $this->assertDatabaseCount('clientes', 1);
            $this->assertDatabaseHas('clientes', [
                'cliente_id' => $client->getKey(), 'numero_documento' => '00123456', 'nombres_razon_social' => 'Ana María',
            ]);
        }
        $this->post('/clients', $this->data(['numero_documento' => '00000002']))->assertSessionHasNoErrors();
        $this->put('/clients/'.$client->getKey(), $this->data(['numero_documento' => '00000003']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['cliente_id' => $client->getKey(), 'numero_documento' => '00000003']);
    }

    public function test_dni_length_rule_does_not_apply_to_other_document_types(): void
    {
        $this->signIn($this->actor());
        foreach (['CE' => 'CE123456789', 'RUC' => '20123456789', 'OTRO' => 'ABC-123'] as $type => $number) {
            $this->post('/clients', $this->data(['tipo_documento' => $type, 'numero_documento' => $number]))
                ->assertSessionHasNoErrors();
            $this->assertDatabaseHas('clientes', ['tipo_documento' => $type, 'numero_documento' => $number]);
        }
    }

    public function test_rf02_and_rf03_reject_blank_fields_and_identify_each_in_form(): void
    {
        $this->signIn($this->actor())->from('/clients/create')->post('/clients', [
            'tipo_documento' => '', 'numero_documento' => '   ', 'nombres_razon_social' => '',
            'direccion' => '   ', 'telefono' => '900123456',
        ])->assertRedirect('/clients/create')->assertSessionHasErrors([
            'tipo_documento', 'numero_documento', 'nombres_razon_social', 'direccion',
        ]);
        $this->withCookie(session()->getName(), session()->getId());
        $response = $this->get('/clients/create')->assertOk()->assertSee('900123456');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['tipo_documento', 'numero_documento', 'nombres_razon_social', 'direccion'] as $field) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$field.'" and @aria-invalid="true" and @aria-describedby="'.$field.'-error"]')->length, $field);
            $this->assertSame(1, $xpath->query('//*[@id="'.$field.'-error"]')->length);
        }
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_invalid_arrays_and_email_are_rejected_and_form_remains_renderable(): void
    {
        $this->signIn($this->actor())->from('/clients/create')->post('/clients', $this->data([
            'tipo_documento' => ['DNI'], 'numero_documento' => ['00123456'],
            'nombres_razon_social' => ['Ana'], 'correo' => 'correo-invalido',
        ]))->assertSessionHasErrors(['tipo_documento', 'numero_documento', 'nombres_razon_social', 'correo']);
        $this->get('/clients/create')->assertOk();
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_rf04_duplicate_document_is_rejected_even_when_existing_client_is_inactive(): void
    {
        $actor = $this->actor();
        $this->client($actor, ['estado' => 'INACTIVO']);
        $this->signIn($actor)->post('/clients', $this->data(['numero_documento' => ' 00123456 ']))
            ->assertSessionHasErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_rf04_edit_allows_own_document_but_rejects_another_clients_document(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $other = $this->client($actor, ['numero_documento' => '87654321']);
        $this->signIn($actor)->put('/clients/'.$client->getKey(), $this->data())->assertSessionHasNoErrors();
        $this->put('/clients/'.$client->getKey(), $this->data([
            'numero_documento' => $other->numero_documento, 'cliente_id' => $other->getKey(),
            'nombres_razon_social' => 'No debe guardarse',
        ]))->assertSessionHasErrors('numero_documento');
        $this->assertSame('00123456', $client->fresh()->numero_documento);
        $this->assertSame('Ana María', $client->fresh()->nombres_razon_social);
    }

    public function test_unique_constraint_race_is_reported_on_the_document_field_for_create_and_update(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->mock(ClientService::class, function (MockInterface $mock): void {
            $exception = new UniqueConstraintViolationException('mysql', 'query', [], new \PDOException('Duplicate entry uq_clientes_documento'));
            $mock->shouldReceive('create')->once()->andThrow($exception);
            $mock->shouldReceive('update')->once()->andThrow($exception);
        });
        $this->signIn($actor)->from('/clients/create')->post('/clients', $this->data(['numero_documento' => '99887766']))
            ->assertRedirect('/clients/create')->assertSessionHasErrors('numero_documento');
        $this->from('/clients/'.$client->getKey().'/edit')->put('/clients/'.$client->getKey(), $this->data())
            ->assertRedirect('/clients/'.$client->getKey().'/edit')->assertSessionHasErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_rf05_searches_full_name_surname_and_document_with_state_filter(): void
    {
        $actor = $this->actor();
        $this->client($actor);
        $this->client($actor, ['numero_documento' => '99999999', 'nombres_razon_social' => 'Otro cliente', 'apellidos' => null, 'estado' => 'INACTIVO']);
        $this->signIn($actor);
        foreach (['Ana María Pérez', 'Pérez Gómez', '00123456'] as $search) {
            $this->get('/clients?'.http_build_query(['search' => $search]))->assertOk()
                ->assertSee('00123456')->assertDontSee('99999999');
        }
        $this->get('/clients?estado=INACTIVO')->assertOk()->assertSee('99999999')->assertDontSee('00123456');
        $this->get('/clients?search=inexistente')->assertOk()->assertSee('No se encontraron clientes');
    }

    public function test_search_treats_wildcards_as_literal_and_retains_filters_on_next_page(): void
    {
        $actor = $this->actor();
        $this->client($actor, ['nombres_razon_social' => 'Ana_%', 'apellidos' => null]);
        $this->client($actor, ['numero_documento' => '9999', 'nombres_razon_social' => 'AnaXYZ', 'apellidos' => null]);
        $this->signIn($actor)->get('/clients?search=Ana_%25')->assertOk()->assertSee('00123456')->assertDontSee('9999');
        for ($i = 0; $i < 17; $i++) {
            $this->client($actor, ['numero_documento' => 'DOC'.$i, 'nombres_razon_social' => 'Equipo '.sprintf('%02d', $i), 'estado' => 'INACTIVO']);
        }
        $this->get('/clients?search=Equipo&estado=INACTIVO')->assertOk()->assertSee('Página 1 de 2')
            ->assertSee('estado=INACTIVO')->assertSee('search=Equipo');
        $this->get('/clients?search=Equipo&estado=INACTIVO&page=2')->assertOk()->assertSee('Equipo 16')->assertDontSee('Equipo 00');
    }

    public function test_rf06_updates_all_profile_data_without_changing_identity_creator_or_state(): void
    {
        $creator = $this->actor();
        $editor = $this->actor(Role::OFFICE);
        $client = $this->client($creator, ['estado' => 'INACTIVO']);
        $created = $client->fresh()->creado_en->toDateTimeString();
        $data = $this->data([
            'tipo_documento' => 'RUC', 'numero_documento' => '00123456789', 'nombres_razon_social' => 'Empresa SAC',
            'apellidos' => 'Gómez', 'direccion' => 'Nueva dirección', 'correo' => 'empresa@example.com',
            'telefono' => '900111222', 'referencia_direccion' => 'Parque', 'distrito' => 'Centro',
            'provincia' => 'Trujillo', 'departamento' => 'La Libertad', 'latitud' => '-8.0000000', 'longitud' => '-79.0000000',
        ]);
        $this->signIn($editor)->put('/clients/'.$client->getKey(), $data + [
            'estado' => 'ACTIVO', 'creado_por' => $editor->getKey(), 'cliente_id' => 999999, 'creado_en' => '2000-01-01',
        ])->assertRedirect('/clients/'.$client->getKey())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', array_diff_key($data, array_flip(['contrasena_router', 'contrasena_wifi'])) + [
            'cliente_id' => $client->getKey(), 'estado' => 'INACTIVO', 'creado_por' => $creator->getKey(), 'creado_en' => $created,
        ]);
        $this->get('/clients/'.$client->getKey().'/edit')->assertOk()->assertSee('Empresa SAC');
    }

    public function test_update_with_blank_required_fields_does_not_persist_partial_changes(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->signIn($actor)->put('/clients/'.$client->getKey(), $this->data([
            'direccion' => '', 'numero_documento' => '', 'nombres_razon_social' => 'Cambio rechazado',
        ]))->assertSessionHasErrors(['direccion', 'numero_documento']);
        $this->assertDatabaseHas('clientes', ['cliente_id' => $client->getKey()] + array_diff_key($this->data(), array_flip(['contrasena_router', 'contrasena_wifi'])));
    }

    public function test_rf07_rf08_rf09_deactivation_retains_client_and_cannot_be_used_to_reactivate(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->signIn($actor)->patch('/clients/'.$client->getKey().'/deactivate', ['direccion' => 'IGNORAR', 'estado' => 'ACTIVO'])
            ->assertRedirect('/clients/'.$client->getKey());
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', ['cliente_id' => $client->getKey(), 'estado' => 'INACTIVO'] + array_diff_key($this->data(), array_flip(['contrasena_router', 'contrasena_wifi'])));
        $this->get('/clients/'.$client->getKey())->assertOk()->assertSee('Inactivo')->assertSee('Av. España 123');
        $this->get('/clients?search=00123456')->assertOk()->assertSee('Inactivo')->assertSee('00123456');
        $updated = $client->fresh()->actualizado_en->toDateTimeString();
        $this->travel(1)->minutes();
        $this->patch('/clients/'.$client->getKey().'/deactivate', ['estado' => 'ACTIVO'])->assertRedirect();
        $this->assertSame($updated, $client->fresh()->actualizado_en->toDateTimeString());
        $this->assertSame('INACTIVO', $client->fresh()->estado);
        $this->delete('/clients/'.$client->getKey())->assertStatus(405);
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_rf10_to_rf13_histories_survive_edit_and_deactivation_and_are_scoped_to_client(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $other = $this->client($actor, ['numero_documento' => '99999999']);
        ClientHistoryFixtures::attach($client, $actor, 'OWN');
        ClientHistoryFixtures::attach($other, $actor, 'FOREIGN');
        $tables = ['contratos', 'pagos', 'pedidos_instalacion'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->signIn($actor)->put('/clients/'.$client->getKey(), $this->data(['direccion' => 'Dirección nueva']))
            ->assertSessionHasNoErrors();
        $this->patch('/clients/'.$client->getKey().'/deactivate')->assertRedirect();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson());
        }
        foreach (['contratos' => 'C', 'pagos' => 'P', 'instalaciones' => 'I'] as $tab => $code) {
            $response = $this->get('/clients/'.$client->getKey().'?tab='.$tab)->assertOk()
                ->assertSee('Inactivo')->assertDontSee('FOREIGN');
            for ($i = 0; $i < 4; $i++) {
                $response->assertSee('OWN-'.$code.'-'.$i);
            }
        }
        $this->get('/clients/'.$client->getKey().'?tab=contratos')->assertSee('Dirección original del contrato')
            ->assertSee('Finalizado')->assertSee('Cancelado')->assertSee('Internet OWN');
        $this->get('/clients/'.$client->getKey().'?tab=pagos')->assertSee('Anulado')->assertSee('Transferencia');
        $this->get('/clients/'.$client->getKey().'?tab=instalaciones')->assertSee('Sin asignar')->assertSee('Sin programar');
    }

    public function test_each_history_paginates_and_keeps_the_selected_tab(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        ClientHistoryFixtures::attach($client, $actor, 'MANY', 17);
        $this->signIn($actor);
        foreach (['contratos' => 'C', 'pagos' => 'P', 'instalaciones' => 'I'] as $tab => $code) {
            $this->get('/clients/'.$client->getKey().'?tab='.$tab)->assertOk()->assertSee('Página 1 de 2')
                ->assertSee('tab='.$tab)->assertSee('MANY-'.$code.'-16')->assertDontSee('MANY-'.$code.'-0');
            $this->get('/clients/'.$client->getKey().'?tab='.$tab.'&page=2')->assertOk()->assertSee('Página 2 de 2')
                ->assertSee('MANY-'.$code.'-0')->assertDontSee('MANY-'.$code.'-16');
        }
    }

    public function test_clients_without_history_have_clear_empty_states(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->signIn($actor);
        foreach (['contratos', 'pagos', 'instalaciones'] as $tab) {
            $this->get('/clients/'.$client->getKey().'?tab='.$tab)->assertOk()->assertSee('Este cliente aún no tiene '.$tab);
        }
    }

    public function test_guest_and_unapproved_role_cannot_access_any_client_operation(): void
    {
        $actor = $this->actor(Role::TECHNICIAN);
        $client = $this->client($actor);
        foreach (['/clients', '/clients/create', '/clients/'.$client->getKey(), '/clients/'.$client->getKey().'/edit'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->put('/clients/'.$client->getKey(), $this->data())->assertRedirect('/login');
        $this->patch('/clients/'.$client->getKey().'/deactivate')->assertRedirect('/login');
        $this->signIn($actor);
        foreach (['/clients', '/clients/create', '/clients/'.$client->getKey(), '/clients/'.$client->getKey().'/edit', '/clients/'.$client->getKey().'?tab=pagos'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put('/clients/'.$client->getKey(), $this->data())->assertForbidden();
        $this->patch('/clients/'.$client->getKey().'/deactivate')->assertForbidden();
        $this->assertSame('ACTIVO', $client->fresh()->estado);
    }

    public function test_office_can_list_consult_and_edit_but_cannot_deactivate_clients(): void
    {
        $actor = $this->actor(Role::OFFICE);
        $client = $this->client($actor);
        $this->signIn($actor)->get('/clients')->assertOk();
        $this->get('/clients/'.$client->getKey())->assertOk()->assertSee('Activo');
        $this->get('/clients/'.$client->getKey().'/edit')->assertOk();
        $this->patch('/clients/'.$client->getKey().'/deactivate')->assertForbidden();
        $this->assertSame('ACTIVO', $client->fresh()->estado);
    }

    public function test_client_mutations_require_csrf_and_inactive_users_cannot_edit(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->signIn($actor);
        $this->app['env'] = 'local';
        $this->post('/clients', $this->data())->assertStatus(419);
        $this->put('/clients/'.$client->getKey(), $this->data())->assertStatus(419);
        $this->patch('/clients/'.$client->getKey().'/deactivate')->assertStatus(419);
        $this->app['env'] = 'testing';
        $actor->update(['estado' => 'INACTIVO']);
        $this->put('/clients/'.$client->getKey(), $this->data())->assertRedirect('/login');
        $this->assertSame('ACTIVO', $client->fresh()->estado);
    }

    public function test_missing_clients_and_invalid_filters_fail_cleanly(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor);
        $this->signIn($actor)->get('/clients/999999')->assertNotFound();
        $this->get('/clients/999999/edit')->assertNotFound();
        $this->put('/clients/999999', $this->data())->assertNotFound();
        $this->patch('/clients/999999/deactivate')->assertNotFound();
        $this->getJson('/clients?search[]=x')->assertUnprocessable();
        $this->getJson('/clients?estado=OTRO')->assertUnprocessable();
        $this->getJson('/clients/'.$client->getKey().'?tab=../../other')->assertUnprocessable();
        $this->getJson('/clients/'.$client->getKey().'?page=-1')->assertUnprocessable();
    }

    public function test_client_content_is_escaped_in_list_detail_and_edit_form(): void
    {
        $actor = $this->actor();
        $client = $this->client($actor, ['nombres_razon_social' => '<script>alert(1)</script>']);
        $this->signIn($actor);
        foreach (['/clients', '/clients/'.$client->getKey(), '/clients/'.$client->getKey().'/edit'] as $url) {
            $this->get($url)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        }
    }
}
