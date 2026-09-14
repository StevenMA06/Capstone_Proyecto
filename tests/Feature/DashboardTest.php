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
        $role = Role::firstOrCreate(['nombre' => 'Gerencia'], ['estado' => 'ACTIVO']);

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    public function test_empty_dashboard_has_six_months_without_invented_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 13));
        $this->signIn($this->manager())->get('/dashboard')->assertOk()
            ->assertSee('Aún no hay ventas confirmadas')->assertSee('Próximamente')
            ->assertViewHas('summary', fn ($s) => count($s['months']) === 6 && $s['salesTotal'] == 0 && $s['months'][0]['label'] === 'abr. 2026');
    }

    public function test_summary_counts_states_and_excludes_cancelled_or_out_of_period_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 13)->startOfDay());
        $manager = $this->manager();
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '00000001', 'nombres_razon_social' => 'Prueba', 'direccion' => 'Dirección', 'estado' => 'INACTIVO', 'creado_por' => $manager->getKey()]);
        ClientHistoryFixtures::attach($client, $manager, 'DASH', 4);
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
            DB::table('materiales')->insert(['codigo' => 'M'.$i, 'nombre' => 'Cable', 'unidad_medida' => 'metro', 'stock_actual' => $stock, 'stock_minimo' => $min, 'estado' => $state, 'creado_por' => $manager->getKey()]);
        }
        $this->signIn($manager)->get('/dashboard')->assertOk()->assertViewHas('clientCount', 1)
            ->assertViewHas('summary', fn ($s) => $s['contracts'] === 1 && $s['payments'] === 2 && $s['installations'] === 1 && $s['equipment'] === 1 && $s['materials'] === 2 && $s['salesTotal'] === 100.25 && $s['months'][0]['total'] === 100.25);
    }

    public function test_staff_does_not_receive_management_statistics(): void
    {
        $this->signIn(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertViewHas('summary', null)->assertSee('Gestionar clientes')->assertDontSee('Resumen de ventas')->assertDontSee('Gestionar usuarios');
        $role = Role::create(['nombre' => 'Técnico', 'estado' => 'ACTIVO']);
        $this->signIn(User::factory()->create(['rol_id' => $role->getKey()]))->get('/dashboard')->assertOk()
            ->assertViewHas('clientCount', null)->assertViewHas('summary', null)->assertDontSee('Clientes registrados');
    }
}
