<?php

namespace Tests\Feature;

use App\Models\CoverageZone;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoverageZoneManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = Role::MANAGER): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', $role)->value('rol_id')]);
    }

    private function plan(User $creator, string $number = 'PLAN-ZONA'): ServicePlan
    {
        return ServicePlan::create([
            'numero_plan' => $number, 'nombre' => 'Fibra Hogar', 'tipo_servicio' => 'Internet',
            'velocidad_mbps' => 200, 'precio_mensual' => 99.90, 'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'codigo' => 'CODIGO-MANUAL-IGNORADO', 'nombre' => 'Zona Norte', 'departamento' => 'La Libertad',
            'provincia' => 'Trujillo', 'distrito' => 'Huanchaco', 'descripcion' => 'Cobertura en el balneario',
        ], $overrides);
    }

    public function test_manager_registers_zone_and_associates_active_plans(): void
    {
        $manager = $this->actor();
        $plan = $this->plan($manager);

        $this->signIn($manager)->post('/zones', $this->data(['plan_ids' => [$plan->getKey()]]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $zone = CoverageZone::firstOrFail();
        $this->assertSame('ZON-ZONA-NORTE-HUANCHACO', $zone->codigo);
        $this->assertSame('ACTIVA', $zone->estado);
        $this->assertDatabaseHas('plan_zona_cobertura', [
            'zona_id' => $zone->getKey(), 'plan_id' => $plan->getKey(), 'estado' => 'ACTIVO',
        ]);
        $this->get('/zones/'.$zone->getKey())->assertOk()->assertSee('Zona Norte')->assertSee('Fibra Hogar');
    }

    public function test_required_fields_are_identified_and_invalid_plan_is_rejected(): void
    {
        $this->signIn($this->actor())->from('/zones/create')->post('/zones', [
            'nombre' => '', 'departamento' => '', 'distrito' => '', 'plan_ids' => [999999],
        ])->assertRedirect('/zones/create')->assertSessionHasErrors(['nombre', 'departamento', 'distrito', 'plan_ids.0']);

        $this->assertDatabaseCount('zonas_cobertura', 0);
    }

    public function test_code_is_readable_automatic_unique_and_does_not_change_when_editing(): void
    {
        $manager = $this->actor();
        $this->signIn($manager)->get('/zones/create')->assertOk()->assertDontSee('Código')->assertDontSee('codigo-preview');
        $this->post('/zones', $this->data(['nombre' => 'El Milagro', 'distrito' => 'Huanchaco']))->assertSessionHasNoErrors();
        $first = CoverageZone::firstOrFail();
        $this->assertSame('ZON-EL-MILAGRO-HUANCHACO', $first->codigo);

        $this->post('/zones', $this->data(['nombre' => 'El Milagro', 'distrito' => 'Huanchaco']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('zonas_cobertura', ['codigo' => 'ZON-EL-MILAGRO-HUANCHACO-02']);

        $this->put('/zones/'.$first->getKey(), $this->data(['codigo' => 'CODIGO-ALTERADO', 'nombre' => 'Nuevo nombre', 'distrito' => 'Moche']))->assertSessionHasNoErrors();
        $this->assertSame('ZON-EL-MILAGRO-HUANCHACO', $first->fresh()->codigo);
    }

    public function test_location_search_coverage_check_and_status_preserve_history(): void
    {
        $manager = $this->actor();
        $plan = $this->plan($manager);
        $zone = CoverageZone::create($this->data(['codigo' => 'ZON_01']) + ['estado' => 'ACTIVA', 'creado_por' => $manager->getKey()]);
        DB::table('plan_zona_cobertura')->insert(['plan_id' => $plan->getKey(), 'zona_id' => $zone->getKey(), 'estado' => 'ACTIVO']);

        $this->signIn($manager)->get('/zones?search=Huanchaco')->assertOk()->assertSee('Zona Norte');
        $this->get('/zones?search=ZON_')->assertOk()->assertSee('ZON_01');
        $this->get('/zones?search=ZONX')->assertOk()->assertDontSee('ZON_01');
        $this->get('/zones?coverage=Huanchaco')->assertOk()->assertSee('Cobertura disponible')->assertSee('Fibra Hogar');

        $this->patch('/zones/'.$zone->getKey().'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVA', $zone->fresh()->estado);
        $this->assertDatabaseHas('plan_zona_cobertura', ['zona_id' => $zone->getKey(), 'plan_id' => $plan->getKey()]);
        $this->get('/zones?coverage=Huanchaco')->assertOk()->assertSee('Sin cobertura disponible');
        $this->get('/contracts/create')->assertOk()->assertDontSee('Zona Norte');
    }

    public function test_office_consults_but_only_management_can_modify_zones(): void
    {
        $manager = $this->actor();
        $zone = CoverageZone::create($this->data() + ['estado' => 'ACTIVA', 'creado_por' => $manager->getKey()]);

        $this->signIn($this->actor(Role::OFFICE))->get('/zones')->assertOk()->assertDontSee('Registrar zona');
        $this->get('/zones/'.$zone->getKey())->assertOk()->assertDontSee('Inhabilitar zona');
        $this->get('/zones/create')->assertForbidden();
        $this->put('/zones/'.$zone->getKey(), $this->data())->assertForbidden();
        $this->patch('/zones/'.$zone->getKey().'/status', ['is_active' => false])->assertForbidden();

        $this->signIn($this->actor(Role::TECHNICIAN))->get('/zones')->assertOk();
        $this->post('/zones', $this->data())->assertForbidden();
    }
}
