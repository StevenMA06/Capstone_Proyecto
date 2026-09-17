<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use App\Services\PlanService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanNumberAndTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')]);
    }

    private function data(): array
    {
        return ['nombre' => 'Fibra', 'tipo_servicio' => 'Internet', 'precio_mensual' => '79.90'];
    }

    public function test_numbers_are_automatic_skip_existing_values_and_cannot_be_edited(): void
    {
        $actor = $this->manager();
        ServicePlan::create($this->data() + ['numero_plan' => 'PLAN-000001', 'creado_por' => $actor->getKey()]);
        $this->signIn($actor)->get('/plans/create')->assertOk()->assertDontSee('id="plan-number"', false)->assertDontSee('name="numero_plan"', false)->assertDontSee('<details class="module-menu"', false);
        $this->post('/plans', $this->data())->assertSessionHasNoErrors();
        $plan = ServicePlan::where('numero_plan', 'PLAN-000002')->firstOrFail();
        $this->put('/plans/'.$plan->getKey(), $this->data() + ['numero_plan' => 'MANIPULADO'])->assertSessionHasNoErrors();
        $this->assertSame('PLAN-000002', $plan->fresh()->numero_plan);
        $this->patch('/plans/'.$plan->getKey().'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->post('/plans', $this->data() + ['numero_plan' => 'MANIPULADO'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('planes_servicio', ['numero_plan' => 'PLAN-000003']);
        $this->assertDatabaseMissing('planes_servicio', ['numero_plan' => 'MANIPULADO']);
    }

    public function test_failed_plan_creation_rolls_back_the_sequence(): void
    {
        $actor = $this->manager();
        try {
            app(PlanService::class)->create(array_merge($this->data(), ['nombre' => null]), $actor);
            $this->fail('Database must reject an invalid plan.');
        } catch (QueryException) {
            $this->assertSame(0, (int) DB::table('secuencias_sistema')->where('nombre', 'planes')->value('valor'));
        }
        $plan = app(PlanService::class)->create($this->data(), $actor);
        $this->assertSame('PLAN-000001', $plan->numero_plan);
    }

    public function test_application_database_and_new_access_logs_use_peru_time(): void
    {
        $this->assertSame('America/Lima', config('app.timezone'));
        $this->assertSame('-05:00', DB::selectOne('SELECT @@session.time_zone AS zone')->zone);
        $this->travelTo(Carbon::parse('2026-09-18 02:30:00', 'UTC'));
        config(['access.two_factor_enabled' => false]);
        $actor = $this->manager();
        $this->post('/login', ['email' => $actor->correo, 'password' => 'ClaveSegura12345'])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('historial_accesos', ['usuario_id' => $actor->getKey(), 'fecha_hora' => '2026-09-17 21:30:00']);
        $this->get('/access-history?date=2026-09-17')->assertOk()->assertSee('21:30:00')->assertSee($actor->nombre_usuario);
        $this->get('/access-history?date=2026-09-18')->assertOk()->assertSee('No se encontraron accesos');
    }
}
