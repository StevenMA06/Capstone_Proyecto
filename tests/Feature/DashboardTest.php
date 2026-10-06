<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::firstOrCreate(['nombre' => Role::MANAGER], ['estado' => 'ACTIVO']);

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    public function test_empty_dashboard_has_six_months_without_invented_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 13));
        $this->signIn($this->manager())->get('/dashboard/gerencia')->assertOk()
            ->assertSee('Aún no hay ventas confirmadas')->assertSee('Próximamente')
            ->assertSee('Aún no hay ventas confirmadas de planes')->assertDontSee('shortcut-grid')
            ->assertViewHas('summary', fn ($s) => count($s['months']) === 6 && $s['salesTotal'] == 0 && $s['months'][0]['label'] === 'abr. 2026');
    }

    public function test_summary_counts_states_and_excludes_cancelled_or_out_of_period_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 13)->startOfDay());
        $manager = $this->manager();
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '00000001', 'nombres_razon_social' => 'Prueba', 'direccion' => 'Dirección', 'estado' => 'INACTIVO', 'creado_por' => $manager->getKey()]);
        ClientHistoryFixtures::attach($client, $manager, 'DASH', 4);
        DB::table('pedidos_instalacion')->where('codigo', 'DASH-I-3')->update(['estado' => 'EN_PROCESO']);
        $contracts = DB::table('contratos')->orderBy('contrato_id')->get();
        foreach ($contracts as $i => $contract) {
            DB::table('ventas')->insert(['contrato_id' => $contract->contrato_id, 'cliente_id' => $client->getKey(), 'responsable_id' => $contract->responsable_id,
                'monto' => [100.25, 200, 300, 400][$i], 'estado' => $i === 1 ? 'ANULADA' : 'CONFIRMADA',
                'fecha_venta' => ['2026-04-01 00:00:00', '2026-05-01 00:00:00', '2026-03-31 23:59:59', '2026-09-14 00:00:00'][$i]]);
        }
        foreach (['DISPONIBLE', 'ASIGNADO'] as $state) {
            DB::table('equipos')->insert(['codigo' => $state, 'nombre' => 'Router', 'tipo' => 'Router', 'estado' => $state, 'creado_por' => $manager->getKey()]);
        }
        foreach ([[1, 2, 'ACTIVO'], [2, 2, 'ACTIVO'], [3, 2, 'ACTIVO'], [0, 2, 'INACTIVO']] as $i => [$stock, $min, $state]) {
            DB::table('materiales')->insert(['codigo' => 'M'.$i, 'nombre' => 'Cable', 'unidad_base' => 'metro', 'stock_actual' => $stock, 'stock_minimo' => $min, 'estado' => $state, 'creado_por' => $manager->getKey()]);
        }
        $this->signIn($manager)->get('/dashboard/gerencia')->assertOk()->assertViewHas('clientCount', 1)
            ->assertViewHas('summary', fn ($s) => $s['topPlans']->count() === 1 && (int) $s['topPlans'][0]->total === 1)
            ->assertViewHas('summary', fn ($s) => $s['contracts'] === 1 && $s['payments'] === 2 && $s['installations'] === 1 && (int) $s['equipment']->first()->stock_actual === 1 && $s['materialsLow'] === 2 && $s['salesTotal'] === 100.25 && $s['months'][0]['total'] === 100.25);
        $this->get('/contracts')->assertOk()->assertSee('DASH-C-0')->assertSee('Prueba');
        $this->get('/payments')->assertOk()->assertSee('DASH-P-0')->assertSee('DASH-C-0');
        $this->get('/installations')->assertOk()->assertSee('DASH-I-3')->assertSee('En proceso');
    }

    public function test_inventory_groups_available_equipment_and_preserves_zero_stock_and_minimum_alerts(): void
    {
        config(['inventory.equipment_minimum' => 1]);
        $manager = $this->manager();
        foreach ([['A', 'DISPONIBLE'], ['A', 'DISPONIBLE'], ['B', 'ASIGNADO'], ['C', 'MANTENIMIENTO'], ['D', 'INACTIVO']] as $i => [$model, $state]) {
            DB::table('equipos')->insert(['codigo' => 'E'.$i, 'nombre' => 'Router <script>', 'tipo' => 'Router', 'modelo' => $model, 'estado' => $state, 'creado_por' => $manager->getKey()]);
        }
        foreach ([[0, 1, 'ACTIVO'], [1, 1, 'ACTIVO'], [1.125, 1, 'ACTIVO'], [0, 1, 'INACTIVO']] as $i => [$stock, $minimum, $state]) {
            DB::table('materiales')->insert(['codigo' => 'MAT'.$i, 'nombre' => 'Fibra <script>', 'unidad_base' => 'm', 'stock_actual' => $stock, 'stock_minimo' => $minimum, 'estado' => $state, 'creado_por' => $manager->getKey()]);
        }
        $this->signIn($manager)->get('/dashboard/gerencia')->assertOk()
            ->assertSee('Router &lt;script&gt;', false)->assertDontSee('Router <script>', false)
            ->assertSee('1.125 m')->assertSee('equipment-dialog')->assertSee('materials-dialog')
            ->assertViewHas('summary', fn ($s) => $s['equipment']->count() === 3 && (int) $s['equipment']->first()->stock_actual === 2 && $s['equipmentLow'] === 2 && $s['materials']->count() === 3 && $s['materialsLow'] === 2);
        $this->get('/equipment')->assertOk()->assertSee('Router &lt;script&gt;', false);
        $this->get('/materials?estado=ACTIVO')->assertOk()->assertSee('1.125')->assertDontSee('MAT3');
    }

    public function test_top_plans_rank_confirmed_sales_by_quantity_and_keep_inactive_plan_history(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 17));
        $manager = $this->manager();
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '99887766', 'nombres_razon_social' => 'Prueba', 'direccion' => 'Prueba', 'creado_por' => $manager->getKey()]);
        for ($i = 1; $i <= 6; $i++) {
            ClientHistoryFixtures::attach($client, $manager, 'TOP'.$i, $i);
        }
        foreach (DB::table('contratos')->get() as $contract) {
            DB::table('ventas')->insert(['contrato_id' => $contract->contrato_id, 'cliente_id' => $client->getKey(), 'responsable_id' => $contract->responsable_id, 'monto' => 90, 'estado' => 'CONFIRMADA', 'fecha_venta' => now()]);
        }
        DB::table('planes_servicio')->where('numero_plan', 'TOP6')->update(['estado' => 'INACTIVO', 'nombre' => '<script>Plan</script>']);
        $this->signIn($manager)->get('/dashboard/gerencia')->assertOk()
            ->assertSee('&lt;script&gt;Plan&lt;/script&gt;', false)->assertDontSee('<script>Plan</script>', false)
            ->assertViewHas('summary', fn ($s) => $s['topPlans']->pluck('numero_plan')->all() === ['TOP6', 'TOP5', 'TOP4', 'TOP3', 'TOP2'] && (int) $s['topPlans'][0]->total === 6 && $s['topPlansMax'] === 6);
    }

    public function test_operation_destinations_are_available_to_management_and_protected_from_other_roles(): void
    {
        foreach (['contracts', 'payments', 'installations', 'equipment', 'materials'] as $path) {
            $this->get('/'.$path)->assertRedirect('/login');
        }
        $this->signIn($this->manager());
        foreach (['contracts', 'payments', 'installations', 'equipment', 'materials'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
        $this->get('/')->assertRedirect('/dashboard');
        $this->signIn(User::factory()->create());
        foreach (['contracts', 'plans', 'installations'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
        foreach (['payments', 'equipment', 'materials'] as $path) {
            $this->get('/'.$path)->assertForbidden();
        }
    }

    public function test_staff_does_not_receive_management_statistics(): void
    {
        $this->signIn(User::factory()->create())->get('/dashboard/gerencia')->assertForbidden();
        $this->get('/dashboard/call-center')->assertOk()->assertDontSee('Resumen de ventas')->assertDontSee('Planes más vendidos');
        $this->signIn(User::factory()->create(['rol_id'=>Role::where('nombre',Role::TECHNICIAN)->value('rol_id')]))->get('/dashboard')->assertRedirect('/technician');
        $this->get('/technician')->assertOk()->assertDontSee('Clientes registrados');
    }
}
