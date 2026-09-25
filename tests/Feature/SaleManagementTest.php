<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class SaleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_combines_filters_updates_charts_and_links_to_contracts(): void
    {
        $actor = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $ana = Client::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '00000001',
            'nombres_razon_social' => 'Ana', 'apellidos' => 'Pérez',
            'direccion' => 'Calle Uno', 'creado_por' => $actor->getKey(),
        ]);
        $luis = Client::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '00000002',
            'nombres_razon_social' => 'Luis', 'apellidos' => 'Rojas',
            'direccion' => 'Calle Dos', 'creado_por' => $actor->getKey(),
        ]);
        ClientHistoryFixtures::attach($ana, $actor, 'VEN-A', 1);
        ClientHistoryFixtures::attach($luis, $actor, 'VEN-L', 1);
        $first = DB::table('contratos')->where('codigo', 'VEN-A-C-0')->first();
        $second = DB::table('contratos')->where('codigo', 'VEN-L-C-0')->first();
        DB::table('ventas')->insert([
            ['contrato_id' => $first->contrato_id, 'cliente_id' => $ana->getKey(), 'responsable_id' => $first->responsable_id, 'fecha_venta' => '2026-09-10 10:00:00', 'monto' => 90, 'estado' => 'CONFIRMADA'],
            ['contrato_id' => $second->contrato_id, 'cliente_id' => $luis->getKey(), 'responsable_id' => $second->responsable_id, 'fecha_venta' => '2026-08-10 10:00:00', 'monto' => 120, 'estado' => 'ANULADA'],
        ]);

        $this->signIn($actor)->get('/sales')->assertOk()
            ->assertSee('Gestión de Ventas')->assertSee('VEN-A-C-0')->assertSee('VEN-L-C-0')
            ->assertSee('S/ 210.00')->assertSee('Conteo de ventas por empleado')
            ->assertSee('Ventas por intervalo de fechas')->assertSee('Distribución por estado')
            ->assertSee('href="'.route('contracts.show', $first->contrato_id).'"', false)
            ->assertSee('href="'.route('contracts.pdf', $first->contrato_id).'"', false);

        $this->get('/sales?cliente=Ana&desde=2026-09-01&hasta=2026-09-30&empleado_id='.$first->responsable_id.'&estados%5B%5D=CONFIRMADA')
            ->assertOk()->assertSee('VEN-A-C-0')->assertDontSee('VEN-L-C-0')
            ->assertSee('S/ 90.00')->assertDontSee('S/ 210.00')
            ->assertSee('10/09');
        $this->get('/sales?estados%5B%5D=ANULADA')->assertOk()->assertSee('VEN-L-C-0')->assertDontSee('VEN-A-C-0');
        $this->get('/sales?desde=2026-09-30&hasta=2026-09-01')->assertSessionHasErrors('hasta');
        $this->get('/sales?estados%5B%5D=ACTIVO')->assertSessionHasErrors('estados.0');
    }

    public function test_sales_requires_office_permission(): void
    {
        $technician = User::factory()->create(['rol_id' => Role::where('nombre', Role::TECHNICIAN)->value('rol_id')]);

        $this->signIn($technician)->get('/sales')->assertForbidden();
    }

    public function test_changing_signed_contract_terms_annuls_sale_until_it_is_confirmed_again(): void
    {
        $actor = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $client = Client::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '00000003',
            'nombres_razon_social' => 'Marta', 'apellidos' => 'Díaz',
            'direccion' => 'Calle Tres', 'distrito' => 'Trujillo', 'creado_por' => $actor->getKey(),
        ]);
        ClientHistoryFixtures::attach($client, $actor, 'VEN-M', 1);
        $contract = DB::table('contratos')->where('codigo', 'VEN-M-C-0')->first();
        DB::table('empleados')->where('empleado_id', $contract->responsable_id)->update(['cargo' => 'Asistente de Ventas']);
        Storage::fake('local');
        Storage::disk('local')->put('contratos/documentos/original.pdf', '%PDF-1.4');
        DB::table('contratos')->where('contrato_id', $contract->contrato_id)->update([
            'firma_revision' => 'VERIFICADA', 'pdf_contrato_ruta' => 'contratos/documentos/original.pdf',
        ]);
        DB::table('ventas')->insert([
            'contrato_id' => $contract->contrato_id, 'cliente_id' => $client->getKey(),
            'responsable_id' => $contract->responsable_id, 'fecha_venta' => '2026-09-10 10:00:00',
            'monto' => 90, 'estado' => 'CONFIRMADA',
        ]);

        $this->signIn($actor)->put('/contracts/'.$contract->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => '2026-01-01', 'duracion_meses' => 12,
            'observaciones' => 'Condiciones nuevas',
        ])->assertSessionHasNoErrors()->assertSessionHas('status_kind', 'pending');

        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE', 'pdf_contrato_ruta' => null]);
        $this->assertDatabaseHas('ventas', ['contrato_id' => $contract->contrato_id, 'estado' => 'ANULADA']);
        $this->assertDatabaseCount('ventas', 1);
    }

    public function test_automatic_cancellation_for_non_payment_annuls_the_sale(): void
    {
        $actor = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $client = Client::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '00000004',
            'nombres_razon_social' => 'Rosa', 'apellidos' => 'López',
            'direccion' => 'Calle Cuatro', 'creado_por' => $actor->getKey(),
        ]);
        ClientHistoryFixtures::attach($client, $actor, 'VEN-I', 1);
        $contract = DB::table('contratos')->where('codigo', 'VEN-I-C-0')->first();
        DB::table('pagos')->where('contrato_id', $contract->contrato_id)->update(['estado' => 'IMPAGO']);
        DB::table('ventas')->insert([
            'contrato_id' => $contract->contrato_id, 'cliente_id' => $client->getKey(),
            'responsable_id' => $contract->responsable_id, 'fecha_venta' => now(),
            'monto' => 90, 'estado' => 'CONFIRMADA',
        ]);

        $this->artisan('contracts:cancel-for-non-payment')->assertSuccessful();

        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'CANCELADO']);
        $this->assertDatabaseHas('ventas', ['contrato_id' => $contract->contrato_id, 'estado' => 'ANULADA']);
    }
}
