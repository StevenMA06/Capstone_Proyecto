<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = Role::MANAGER): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', $role)->value('rol_id')]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['numero_plan' => 'PLAN-100', 'nombre' => 'Internet Hogar', 'tipo_servicio' => 'Internet', 'velocidad_mbps' => 100, 'precio_mensual' => '89.90', 'descripcion' => 'Fibra óptica', 'condiciones' => 'Condiciones del servicio'], $overrides);
    }

    private function plan(User $user, array $overrides = []): ServicePlan
    {
        return ServicePlan::create($this->data($overrides) + ['creado_por' => $user->getKey()]);
    }

    public function test_administrators_and_managers_register_complete_active_plans(): void
    {
        foreach ([Role::ADMIN, Role::MANAGER] as $i => $role) {
            $actor = $this->actor($role);
            $this->signIn($actor)->post('/plans', $this->data(['numero_plan' => 'PLAN-'.$i, 'estado' => 'INACTIVO', 'creado_por' => 9999]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('planes_servicio', ['numero_plan' => 'PLAN-'.$i, 'estado' => 'ACTIVO', 'precio_mensual' => '89.90', 'creado_por' => $actor->getKey()]);
        }
    }

    public function test_required_fields_are_rejected_and_identified_in_the_form(): void
    {
        $this->signIn($this->actor())->from('/plans/create')->post('/plans', ['numero_plan' => '   ', 'nombre' => '', 'tipo_servicio' => '', 'precio_mensual' => ''])
            ->assertRedirect('/plans/create')->assertSessionHasErrors(['numero_plan', 'nombre', 'tipo_servicio', 'precio_mensual']);
        $this->withViewErrors(session('errors')->getMessages())->view('plans.form', ['plan' => new ServicePlan])
            ->assertSee('aria-invalid="true"', false)->assertSee('numero_plan-error')->assertSee('nombre-error')->assertSee('tipo_servicio-error')->assertSee('precio_mensual-error');
        $this->assertDatabaseCount('planes_servicio', 0);
    }

    public function test_invalid_prices_speed_and_duplicate_numbers_cannot_be_saved(): void
    {
        $actor = $this->actor();
        $plan = $this->plan($actor);
        $this->signIn($actor)->post('/plans', $this->data())->assertSessionHasErrors('numero_plan');
        foreach (['0', '-1', '1.001', '10000000000', '1e3', 'texto'] as $price) {
            $this->post('/plans', $this->data(['numero_plan' => 'OTRO', 'precio_mensual' => $price]))->assertSessionHasErrors('precio_mensual');
        }
        $this->post('/plans', $this->data(['numero_plan' => 'OTRO', 'velocidad_mbps' => -1]))->assertSessionHasErrors('velocidad_mbps');
        $this->put('/plans/'.$plan->getKey(), $this->data(['nombre' => '', 'precio_mensual' => '0']))->assertSessionHasErrors(['nombre', 'precio_mensual']);
        $this->assertSame('89.90', $plan->fresh()->precio_mensual);
        $this->assertDatabaseCount('planes_servicio', 1);
    }

    public function test_detail_and_edit_show_all_fields_and_escape_stored_text(): void
    {
        $actor = $this->actor();
        $plan = $this->plan($actor, ['condiciones' => '<script>alert(1)</script>']);
        $this->signIn($actor)->get('/plans/'.$plan->getKey())->assertOk()->assertSee('PLAN-100')->assertSee('Internet Hogar')->assertSee('100 Mbps')->assertSee('89.90')->assertSee('Fibra óptica')->assertSee($actor->name)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/plans/'.$plan->getKey().'/edit')->assertOk()->assertSee('Internet Hogar');
        $this->put('/plans/'.$plan->getKey(), $this->data(['nombre' => 'Nuevo nombre', 'precio_mensual' => '99.50', 'velocidad_mbps' => null, 'descripcion' => null, 'condiciones' => null]))->assertSessionHasNoErrors()->assertRedirect('/plans/'.$plan->getKey());
        $this->assertDatabaseHas('planes_servicio', ['plan_id' => $plan->getKey(), 'nombre' => 'Nuevo nombre', 'precio_mensual' => '99.50', 'velocidad_mbps' => null, 'descripcion' => null]);
    }

    public function test_status_changes_preserve_plan_and_existing_contracts(): void
    {
        $actor = $this->actor();
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '00000001', 'nombres_razon_social' => 'Cliente', 'direccion' => 'Dirección', 'creado_por' => $actor->getKey()]);
        ClientHistoryFixtures::attach($client, $actor, 'PLAN-HISTORY', 1);
        $plan = ServicePlan::firstOrFail();
        $before = $plan->getAttributes();
        $contracts = DB::table('contratos')->get()->toArray();
        $this->signIn($actor)->patch('/plans/'.$plan->getKey().'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $plan->fresh()->estado);
        foreach ($before as $key => $value) {
            if (! in_array($key, ['estado', 'actualizado_en'])) {
                $this->assertEquals($value, $plan->fresh()->getAttributes()[$key]);
            }
        }
        $this->get('/plans/'.$plan->getKey())->assertOk()->assertSee('Inactivo');
        $this->put('/plans/'.$plan->getKey(), $this->data(['numero_plan' => $plan->numero_plan, 'precio_mensual' => '500.00']))->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $plan->fresh()->estado);
        $this->assertEquals($contracts, DB::table('contratos')->get()->toArray());
        $this->patch('/plans/'.$plan->getKey().'/status', ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame('ACTIVO', $plan->fresh()->estado);
        $this->delete('/plans/'.$plan->getKey())->assertStatus(405);
        $this->assertDatabaseCount('planes_servicio', 1);
    }

    public function test_number_search_is_literal_combines_state_and_paginates(): void
    {
        $actor = $this->actor();
        for ($i = 0; $i < 17; $i++) {
            $this->plan($actor, ['numero_plan' => sprintf('NET_%02d', $i), 'estado' => 'INACTIVO']);
        }
        $this->plan($actor, ['numero_plan' => 'NETX99']);
        $this->signIn($actor)->get('/plans?search=NET_&estado=INACTIVO')->assertOk()->assertSee('NET_00')->assertDontSee('NETX99')->assertSee('Página 1 de 2')->assertSee('estado=INACTIVO', false);
        $this->get('/plans?search=NET_&estado=INACTIVO&page=2')->assertSee('NET_16')->assertDontSee('NET_00');
        $this->get('/plans?search=NO-EXISTE')->assertSee('No se encontraron planes');
        $this->get('/plans?estado=INVALIDO')->assertSessionHasErrors('estado');
        $this->get('/plans/999999')->assertNotFound();
    }

    public function test_office_can_consult_but_cannot_mutate_and_technicians_cannot_access(): void
    {
        $plan = $this->plan($this->actor());
        $this->get('/plans')->assertRedirect('/login');
        $this->post('/plans', $this->data())->assertRedirect('/login');
        $this->signIn($this->actor(Role::OFFICE))->get('/plans')->assertOk()->assertDontSee('Registrar plan')->assertDontSee('>Editar<', false);
        $this->get('/plans/'.$plan->getKey())->assertOk()->assertDontSee('Inhabilitar plan');
        $this->get('/plans/create')->assertForbidden();
        $this->get('/plans/'.$plan->getKey().'/edit')->assertForbidden();
        $this->post('/plans', $this->data())->assertForbidden();
        $this->put('/plans/'.$plan->getKey(), $this->data())->assertForbidden();
        $this->patch('/plans/'.$plan->getKey().'/status', ['is_active' => false])->assertForbidden();
        $this->signIn($this->actor(Role::TECHNICIAN))->get('/plans')->assertForbidden();
        $this->get('/plans/'.$plan->getKey())->assertForbidden();
    }

    public function test_contract_form_and_submission_only_allow_active_plans_and_snapshot_the_price(): void
    {
        $actor = $this->actor(Role::OFFICE);
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '00000002', 'nombres_razon_social' => 'Cliente', 'direccion' => 'Dirección', 'creado_por' => $actor->getKey()]);
        ClientHistoryFixtures::attach($client, $actor, 'SELECT', 1);
        $active = ServicePlan::firstOrFail();
        $inactive = $this->plan($actor, ['estado' => 'INACTIVO', 'nombre' => 'Plan oculto']);
        $existing = DB::table('contratos')->first();
        $data = ['codigo' => 'CONTRATO-NUEVO', 'cliente_id' => $client->getKey(), 'zona_id' => $existing->zona_id, 'responsable_id' => $existing->responsable_id, 'fecha_inicio' => '2026-09-01', 'duracion_meses' => 12, 'direccion_servicio' => 'Dirección', 'plan_id' => $active->getKey(), 'precio_contratado' => '0.01', 'estado' => 'ACTIVO'];
        $this->signIn($actor)->get('/contracts/create')->assertOk()->assertSee($active->nombre)->assertDontSee('Plan oculto')->assertViewHas('plans', fn ($p) => $p->pluck('plan_id')->all() === [$active->getKey()]);
        $this->post('/contracts', array_merge($data, ['plan_id' => $inactive->getKey()]))->assertSessionHasErrors('plan_id');
        $this->post('/contracts', array_merge($data, ['plan_id' => 999999]))->assertSessionHasErrors('plan_id');
        $this->post('/contracts', $data)->assertSessionHasNoErrors()->assertRedirect();
        $saved = DB::table('contratos')->where('contrato_id', '!=', $existing->contrato_id)->first();
        $this->assertMatchesRegularExpression('/^CON-[0-9]{8}-[A-Z0-9]{8}$/', $saved->codigo);
        $this->assertDatabaseMissing('contratos', ['codigo' => 'CONTRATO-NUEVO']);
        $this->assertDatabaseHas('contratos', ['contrato_id' => $saved->contrato_id, 'plan_id' => $active->getKey(), 'precio_contratado' => '90.00', 'estado' => 'PENDIENTE', 'fecha_fin' => '2027-08-31']);
        $active->update(['estado' => 'INACTIVO']);
        $this->get('/contracts/create')->assertOk()->assertSee('No hay planes activos');
        try {
            app(ContractService::class)->create(array_merge($data, ['codigo' => 'STALE']));
            $this->fail('Inactive plan must be rechecked under lock.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('plan_id', $error->errors());
        }
        $this->assertDatabaseCount('contratos', 2);
        $this->signIn($this->actor(Role::TECHNICIAN))->get('/contracts/create')->assertForbidden();
        $this->post('/contracts', $data)->assertForbidden();
    }
}
