<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $actor = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $client = Client::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '76543210',
            'nombres_razon_social' => 'Ana', 'apellidos' => 'Pérez',
            'direccion' => 'Calle Uno', 'distrito' => 'Trujillo', 'creado_por' => $actor->getKey(),
        ]);
        ClientHistoryFixtures::attach($client, $actor, 'RF-CON', 1);
        $contract = DB::table('contratos')->where('codigo', 'RF-CON-C-0')->first();
        DB::table('empleados')->where('empleado_id', $contract->responsable_id)
            ->update(['cargo' => 'Asistente de Ventas']);

        return [$actor, $client, $contract];
    }

    public function test_contracts_can_be_searched_filtered_updated_and_downloaded(): void
    {
        [$actor, $client, $contract] = $this->records();
        $this->signIn($actor)->get('/contracts?search=76543210&estado=ACTIVO&desde=2026-01-01&hasta=2026-01-31')
            ->assertOk()->assertSee('RF-CON-C-0')->assertSee('Ana Pérez');
        $this->get('/contracts?search=76543210&estado=CANCELADO')->assertOk()->assertDontSee('>RF-CON-C-0<', false);
        $this->get('/contracts/'.$contract->contrato_id)->assertOk()->assertSee('Internet RF-CON');
        $this->get('/contracts/'.$contract->contrato_id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-1.4', false);

        $this->patch('/contracts/'.$contract->contrato_id.'/status', ['estado' => 'ACTIVO'])->assertNotFound();
        Storage::fake('local');
        DB::table('clientes')->where('cliente_id', $client->getKey())->update(['direccion' => 'Calle Dos']);
        $this->put('/contracts/'.$contract->contrato_id, [
            'codigo' => 'RF-CON-C-0', 'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => '2026-02-01', 'duracion_meses' => 6,
            'direccion_servicio' => 'Dirección falsificada', 'observaciones' => 'Cambio de domicilio',
        ])->assertSessionHasNoErrors()->assertRedirect('/contracts/'.$contract->contrato_id);
        $this->assertDatabaseHas('contratos', [
            'contrato_id' => $contract->contrato_id, 'fecha_fin' => '2026-07-31',
            'precio_contratado' => '90.00', 'direccion_servicio' => 'Calle Dos',
        ]);
        $this->patch('/contracts/'.$contract->contrato_id.'/status', ['estado' => 'ACTIVO'])->assertNotFound();
        $this->patch('/contracts/'.$contract->contrato_id.'/status', ['estado' => 'FINALIZADO'])->assertNotFound();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE']);
    }

    public function test_contract_rejects_client_district_and_plan_coverage_mismatches(): void
    {
        [$actor, $client, $contract] = $this->records();
        $data = [
            'codigo' => 'NEW-COVERAGE', 'cliente_id' => $client->getKey(),
            'plan_id' => $contract->plan_id, 'zona_id' => $contract->zona_id,
            'responsable_id' => $contract->responsable_id, 'fecha_inicio' => '2026-09-01',
            'duracion_meses' => 12, 'direccion_servicio' => 'Calle Uno',
        ];
        $this->signIn($actor);
        DB::table('clientes')->where('cliente_id', $client->getKey())->update(['distrito' => 'Lima']);
        $this->post('/contracts', $data)->assertSessionHasErrors('zona_id');
        DB::table('clientes')->where('cliente_id', $client->getKey())->update(['distrito' => 'Trujillo']);
        DB::table('plan_zona_cobertura')->insert(['plan_id' => $contract->plan_id, 'zona_id' => $contract->zona_id, 'estado' => 'INACTIVO']);
        $this->post('/contracts', $data)->assertSessionHasErrors('zona_id');
        $this->assertDatabaseMissing('contratos', ['codigo' => 'NEW-COVERAGE']);
    }

    public function test_responsible_options_only_include_active_sales_assistants_and_server_rechecks_them(): void
    {
        [$actor, $client, $contract] = $this->records();
        $technician = Employee::create([
            'tipo_documento' => 'OTRO', 'numero_documento' => 'TECH-CON',
            'nombres' => 'Técnico', 'apellidos' => 'Instalador', 'cargo' => 'Instalador',
        ]);
        $inactive = Employee::create([
            'tipo_documento' => 'OTRO', 'numero_documento' => 'INACT-CON',
            'nombres' => 'Asistente', 'apellidos' => 'Inactivo',
            'cargo' => 'Asistente de Ventas', 'estado' => 'INACTIVO',
        ]);
        $this->signIn($actor)->get('/contracts/create')->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->pluck('empleado_id')->all() === [$contract->responsable_id])
            ->assertSee('value="'.$contract->responsable_id.'"', false)
            ->assertDontSee('value="'.$technician->getKey().'"', false)
            ->assertDontSee('value="'.$inactive->getKey().'"', false);

        $data = [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'fecha_inicio' => '2026-10-01',
            'duracion_meses' => 12,
        ];
        $this->post('/contracts', $data + ['responsable_id' => $technician->getKey()])
            ->assertSessionHasErrors('responsable_id');
        $this->post('/contracts', $data + ['responsable_id' => $inactive->getKey()])
            ->assertSessionHasErrors('responsable_id');
        $this->put('/contracts/'.$contract->contrato_id, $data + ['responsable_id' => $technician->getKey()])
            ->assertSessionHasErrors('responsable_id');
        DB::table('empleados')->where('empleado_id', $contract->responsable_id)->update(['cargo' => 'Instalador']);
        $this->get('/contracts/'.$contract->contrato_id.'/edit')->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->isEmpty());
        $this->put('/contracts/'.$contract->contrato_id, $data + ['responsable_id' => $contract->responsable_id])
            ->assertSessionHasErrors('responsable_id');
        $this->assertDatabaseCount('contratos', 1);
    }

    public function test_code_is_generated_and_unsigned_contract_cannot_leave_pending(): void
    {
        [$actor, $client, $existing] = $this->records();
        $response = $this->signIn($actor)->post('/contracts', [
            'codigo' => 'MANUAL', 'estado' => 'ACTIVO', 'cliente_id' => $client->getKey(),
            'plan_id' => $existing->plan_id, 'zona_id' => $existing->zona_id,
            'responsable_id' => $existing->responsable_id, 'fecha_inicio' => '2026-10-01',
            'duracion_meses' => 12, 'direccion_servicio' => 'Calle Tres',
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $saved = DB::table('contratos')->where('contrato_id', '!=', $existing->contrato_id)->first();
        $this->assertMatchesRegularExpression('/^CON-[0-9]{8}-[A-Z0-9]{8}$/', $saved->codigo);
        $this->assertSame('PENDIENTE', $saved->estado);
        $this->assertNull($saved->pdf_contrato_ruta);
        $this->assertSame('Calle Uno', $saved->direccion_servicio);
        $this->get('/contracts/create')->assertOk()->assertDontSee('name="direccion_servicio"', false);
        $this->get('/contracts/'.$saved->contrato_id.'/edit')->assertOk()->assertSee('value="Calle Uno"', false);
        $this->get('/contracts/'.$saved->contrato_id.'/edit')->assertDontSee('name="estado"', false);
        $this->put('/contracts/'.$saved->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $saved->plan_id,
            'zona_id' => $saved->zona_id, 'responsable_id' => $saved->responsable_id,
            'fecha_inicio' => '2026-10-01', 'duracion_meses' => 12,
            'estado' => 'CANCELADO',
        ])->assertSessionHasErrors('estado');
        $this->put('/contracts/'.$saved->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $saved->plan_id,
            'zona_id' => $saved->zona_id, 'responsable_id' => $saved->responsable_id,
            'fecha_inicio' => '2026-10-01', 'duracion_meses' => 12,
            'estado' => 'ACTIVO',
        ])->assertSessionHasErrors('estado');
        $this->patch('/contracts/'.$saved->contrato_id.'/status', ['estado' => 'CANCELADO'])->assertNotFound();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $saved->contrato_id, 'estado' => 'PENDIENTE']);
    }

    public function test_signed_document_upload_is_only_available_when_updating(): void
    {
        [$actor, $client, $existing] = $this->records();
        $this->signIn($actor)->get('/contracts/create')->assertOk()->assertDontSee('id="documento_pdf"', false);
        $this->get('/contracts/'.$existing->contrato_id.'/edit')->assertOk()->assertSee('id="documento_pdf"', false);

        Storage::fake('local');
        $this->post('/contracts', [
            'cliente_id' => $client->getKey(), 'plan_id' => $existing->plan_id,
            'zona_id' => $existing->zona_id, 'responsable_id' => $existing->responsable_id,
            'fecha_inicio' => '2026-10-01', 'duracion_meses' => 12,
            'direccion_servicio' => 'Calle Tres',
            'documento_pdf' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4\nDocumento firmado"),
        ])->assertSessionHasErrors('documento_pdf');
        $this->assertDatabaseCount('contratos', 1);
    }

    public function test_empty_pdf_has_a_specific_validation_message(): void
    {
        [$actor, $client, $contract] = $this->records();
        $this->signIn($actor)->put('/contracts/'.$contract->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => '2026-01-01', 'duracion_meses' => 12,
            'documento_pdf' => UploadedFile::fake()->createWithContent('vacio.pdf', ''),
        ])->assertSessionHasErrors([
            'documento_pdf' => 'El archivo seleccionado está vacío (0 bytes). Vuelve a exportar el PDF firmado e inténtalo de nuevo.',
        ]);
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'pdf_contrato_ruta' => null]);
    }

    public function test_verified_contract_can_be_finalized_from_edit_after_its_end_date(): void
    {
        [$actor, $client, $contract] = $this->records();
        Storage::fake('local');
        Storage::disk('local')->put('contratos/documentos/verificado.pdf', '%PDF-1.4');
        DB::table('clientes')->where('cliente_id', $client->getKey())->update(['direccion' => 'Dirección original del contrato']);
        DB::table('contratos')->where('contrato_id', $contract->contrato_id)->update([
            'pdf_contrato_ruta' => 'contratos/documentos/verificado.pdf',
            'firma_revision' => 'VERIFICADA',
        ]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-01 12:00:00'));
        $this->signIn($actor)->get('/contracts/'.$contract->contrato_id.'/edit')
            ->assertOk()->assertSee('value="FINALIZADO"', false);
        $this->put('/contracts/'.$contract->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => '2026-01-01', 'duracion_meses' => 12,
            'observaciones' => 'Contrato de prueba RF-CON', 'estado' => 'FINALIZADO',
        ])->assertSessionHasNoErrors()->assertSessionHas('status_kind', 'finalized');
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'FINALIZADO']);
    }
}
