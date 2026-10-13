<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $actor = User::factory()->create(['rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')]);
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
        $this->get('/contracts/'.$contract->contrato_id)->assertOk()->assertSee('Internet RF-CON')
            ->assertDontSee('Registrar venta mediante contrato firmado')
            ->assertDontSee('Archivos de campo anteriores');
        $this->get('/contracts/'.$contract->contrato_id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-1.4', false);
        $this->get('/contracts/'.$contract->contrato_id.'/draft')->assertDownload('contrato-'.$contract->codigo.'-para-firma.pdf');

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

    public function test_contract_cancellation_requires_evidence_and_a_different_authorized_reviewer(): void
    {
        [$actor, $client, $contract] = $this->records();
        Storage::fake('local');
        Storage::disk('local')->put('contracts/signed.pdf', '%PDF-1.4 signed');
        DB::table('contratos')->where('contrato_id', $contract->contrato_id)->update([
            'direccion_servicio' => $client->direccion,
            'pdf_contrato_ruta' => 'contracts/signed.pdf',
            'firma_revision' => 'VERIFICADA',
            'firma_validada_en' => now(),
        ]);
        $periodId = DB::table('periodos_contractuales')->insertGetId([
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 1,
            'fecha_inicio_periodo' => $contract->fecha_inicio,
            'fecha_fin_periodo' => $contract->fecha_fin,
            'duracion_meses_periodo' => $contract->duracion_meses,
            'estado_periodo' => 'ACTIVO',
        ]);
        DB::table('contratos')->where('contrato_id', $contract->contrato_id)->update(['periodo_actual' => $periodId]);

        $this->signIn($actor)->get('/contracts/'.$contract->contrato_id.'/edit')->assertOk()
            ->assertSeeInOrder(['workspace-breadcrumb', 'GESTIÓN COMERCIAL'])
            ->assertSee('Solicitar cancelación del contrato')
            ->assertSee('Evidencia de respaldo')
            ->assertDontSee('name="estado"', false);

        $this->put('/contracts/'.$contract->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => $contract->fecha_inicio, 'duracion_meses' => $contract->duracion_meses,
            'estado' => 'CANCELADO', 'motivo_cancelacion' => 'Solicitud del cliente',
        ])->assertSessionHasErrors(['estado', 'motivo_cancelacion']);
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'ACTIVO']);

        $reason = 'El cliente solicitó la baja por cambio de domicilio.';
        $evidence = UploadedFile::fake()->createWithContent('solicitud.pdf', "%PDF-1.4\nEvidencia de solicitud de baja\n");
        $this->post('/contracts/'.$contract->contrato_id.'/cancellation-requests', [
            'categoria' => 'Solicitud del cliente', 'explicacion' => $reason, 'evidencia' => $evidence,
        ])->assertSessionHasNoErrors()->assertRedirect('/contracts/'.$contract->contrato_id);
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'ACTIVO']);
        $this->put('/contracts/'.$contract->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => $contract->fecha_inicio, 'duracion_meses' => $contract->duracion_meses,
        ])->assertSessionHasErrors('contrato');

        $requestId = DB::table('solicitudes_cancelacion_contrato')->value('solicitud_cancelacion_id');
        $this->post('/contracts/'.$contract->contrato_id.'/cancellation-requests/'.$requestId.'/approve')
            ->assertSessionHasErrors('solicitud');
        $reviewer = User::factory()->create(['rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')]);
        $this->signIn($reviewer)->get('/contracts/'.$contract->contrato_id.'/cancellation-requests/'.$requestId.'/evidence')
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/contracts/'.$contract->contrato_id.'/cancellation-requests/'.$requestId.'/approve')
            ->assertSessionHasNoErrors()->assertRedirect('/contracts/'.$contract->contrato_id);
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'CANCELADO']);
        $this->assertDatabaseHas('periodos_contractuales', [
            'periodo_id' => $periodId, 'estado_periodo' => 'CANCELADO',
            'motivo_cancelacion' => 'Solicitud del cliente — '.$reason, 'origen_cancelacion' => 'MANUAL',
            'cancelado_por' => $reviewer->getKey(),
        ]);
        $this->get('/contracts/'.$contract->contrato_id)->assertOk()->assertSee($reason)
            ->assertSee('únicamente para consulta')->assertDontSee('Actualizar contrato')
            ->assertSee($actor->name)->assertSee($reviewer->name);
        $this->assertDatabaseHas('solicitudes_cancelacion_contrato', [
            'solicitud_cancelacion_id' => $requestId, 'estado' => 'APROBADA',
            'solicitado_por' => $actor->getKey(), 'resuelto_por' => $reviewer->getKey(),
            'evidencia_sha256' => hash('sha256', "%PDF-1.4\nEvidencia de solicitud de baja\n"),
        ]);
        $this->get('/contracts/'.$contract->contrato_id.'/edit')->assertForbidden();
        $this->put('/contracts/'.$contract->contrato_id, $data + ['motivo_cancelacion' => 'Intento posterior'])->assertForbidden();
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
            ->assertDontSee('Técnico Instalador')
            ->assertDontSee('Asistente Inactivo');

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

    public function test_office_contract_creator_is_automatically_responsible_and_cannot_override_it(): void
    {
        [$manager, $client, $existing] = $this->records();
        $assistant = Employee::create([
            'tipo_documento' => 'OTRO', 'numero_documento' => 'OFFICE-CON',
            'nombres' => 'Rosa', 'apellidos' => 'Call Center', 'cargo' => 'Asistente de Ventas',
        ]);
        $officeRole = Role::where('nombre', Role::OFFICE)->value('rol_id');
        $officeUser = User::factory()->create(['rol_id' => $officeRole, 'empleado_id' => $assistant->getKey()]);
        $this->signIn($officeUser)->get('/contracts/create')->assertOk()
            ->assertSee('Rosa Call Center')->assertDontSee('name="responsable_id"', false);

        $data = [
            'cliente_id' => $client->getKey(), 'plan_id' => $existing->plan_id,
            'zona_id' => $existing->zona_id, 'fecha_inicio' => '2026-10-12',
            'duracion_meses' => 12,
        ];
        $this->post('/contracts', $data + ['responsable_id' => $existing->responsable_id])
            ->assertSessionHasErrors('responsable_id');
        $this->post('/contracts', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('contratos', [
            'cliente_id' => $client->getKey(), 'responsable_id' => $assistant->getKey(),
        ]);
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
        $this->get('/contracts/'.$saved->contrato_id)->assertOk()
            ->assertSee('Descargar contrato para firma')
            ->assertDontSee('Archivos de este contrato');
        $this->assertMatchesRegularExpression('/^CON-[0-9]{8}-[A-Z0-9]{8}$/', $saved->codigo);
        $this->assertSame('PENDIENTE', $saved->estado);
        $this->assertNull($saved->pdf_contrato_ruta);
        $this->assertSame('Calle Uno', $saved->direccion_servicio);
        $this->get('/contracts/create')->assertOk()->assertDontSee('name="direccion_servicio"', false);
        $this->get('/contracts/'.$saved->contrato_id.'/edit')->assertOk()->assertSee('value="Calle Uno"', false);
        $this->get('/contracts/'.$saved->contrato_id.'/edit')->assertDontSee('name="estado"', false)
            ->assertSee('Pendiente', false)->assertDontSee('value="ACTIVO"', false);
        // Isolate this new customer service so canceling its only pending work
        // proves the client no longer remains permanently pending installation.
        DB::table('pedidos_instalacion')->where('contrato_id', '<>', $saved->contrato_id)->delete();
        DB::table('pagos')->where('contrato_id', '<>', $saved->contrato_id)->delete();
        DB::table('contratos')->where('contrato_id', '<>', $saved->contrato_id)->delete();
        $this->put('/contracts/'.$saved->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $saved->plan_id,
            'zona_id' => $saved->zona_id, 'responsable_id' => $saved->responsable_id,
            'fecha_inicio' => '2026-10-01', 'duracion_meses' => 12,
            'estado' => 'ACTIVO',
        ])->assertSessionHasErrors('estado');
        Storage::fake('local');
        $this->post('/contracts/'.$saved->contrato_id.'/cancellation-requests', [
            'categoria' => 'Solicitud del cliente',
            'explicacion' => 'El cliente decidió no continuar con la instalación.',
            'evidencia' => UploadedFile::fake()->createWithContent('baja.pdf', "%PDF-1.4\nSolicitud del cliente\n"),
        ])->assertSessionHasNoErrors();
        $requestId = DB::table('solicitudes_cancelacion_contrato')->value('solicitud_cancelacion_id');
        $reviewer = User::factory()->create(['rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')]);
        $this->signIn($reviewer)->post('/contracts/'.$saved->contrato_id.'/cancellation-requests/'.$requestId.'/approve')
            ->assertSessionHasNoErrors()->assertRedirect('/contracts/'.$saved->contrato_id);
        $this->patch('/contracts/'.$saved->contrato_id.'/status', ['estado' => 'CANCELADO'])->assertNotFound();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $saved->contrato_id, 'estado' => 'CANCELADO']);
        $this->assertDatabaseHas('periodos_contractuales', ['contrato_id' => $saved->contrato_id, 'estado_periodo' => 'CANCELADO', 'motivo_cancelacion' => 'Solicitud del cliente — El cliente decidió no continuar con la instalación.']);
        $orderId = DB::table('pedidos_instalacion')->where('contrato_id', $saved->contrato_id)->value('pedido_id');
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $orderId, 'estado' => 'CANCELADO']);
        $this->assertDatabaseHas('pedido_historial_eventos', [
            'pedido_id' => $orderId, 'tipo' => 'CANCELACION', 'motivo' => 'Solicitud del cliente — El cliente decidió no continuar con la instalación.',
        ]);
        $this->assertDatabaseHas('clientes', ['cliente_id' => $client->getKey(), 'estado' => 'INACTIVO']);
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

    public function test_office_confirms_sale_without_permission_to_change_contract_terms(): void
    {
        [$manager,$client,$contract]=$this->records();
        Storage::fake('local');
        DB::table('contratos')->where('contrato_id',$contract->contrato_id)->update(['estado'=>'PENDIENTE','direccion_servicio'=>$client->direccion]);
        DB::table('pedidos_instalacion')->where('contrato_id', $contract->contrato_id)->delete();
        $office=User::factory()->create(['rol_id'=>Role::where('nombre',Role::OFFICE)->value('rol_id')]);
        $this->mock(ContractSignatureVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturn(['status'=>'signed']));
        $this->signIn($office)->post('/sales/contracts/'.$contract->contrato_id.'/confirm',[
            'documento_pdf'=>UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4\nDocumento firmado"),
            'duracion_meses'=>1,'estado'=>'CANCELADO','precio_contratado'=>0,
        ])->assertSessionHasNoErrors()->assertRedirect('/contracts/'.$contract->contrato_id);
        $this->assertDatabaseHas('contratos',['contrato_id'=>$contract->contrato_id,'estado'=>'ACTIVO','duracion_meses'=>$contract->duracion_meses,'precio_contratado'=>$contract->precio_contratado]);
        $this->assertDatabaseHas('ventas',['contrato_id'=>$contract->contrato_id,'estado'=>'CONFIRMADA']);
        $this->assertDatabaseHas('pedidos_instalacion', [
            'contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE',
            'fase_flujo' => 'PENDIENTE_COORDINACION', 'tecnico_id' => null,
        ]);
        $this->put('/contracts/'.$contract->contrato_id,[])->assertForbidden();
    }

    public function test_confirmed_visit_creates_order_before_the_contract_is_signed(): void
    {
        [, $client, $contract] = $this->records();
        DB::table('pedidos_instalacion')->where('contrato_id', $contract->contrato_id)->delete();
        DB::table('contratos')->where('contrato_id', $contract->contrato_id)
            ->update(['estado' => 'PENDIENTE', 'pdf_contrato_ruta' => null, 'firma_revision' => null]);
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $this->signIn($office)->get('/dashboard/call-center/visitas')->assertOk()
            ->assertSee('Contratos por coordinar')->assertSee($contract->codigo);
        $visit = now('America/Lima')->addDays(3)->setTime(9, 0);
        $data = [
            'fecha_visita' => $visit->format('Y-m-d'), 'hora_inicio' => '09:00',
            'franja_horaria' => '08:00 a 12:00', 'canal_coordinacion' => 'LLAMADA',
        ];
        $url = '/dashboard/call-center/visitas/contratos/'.$contract->contrato_id;
        $this->post($url, $data)->assertSessionHasErrors('aceptacion_cliente');
        $this->assertDatabaseMissing('pedidos_instalacion', ['contrato_id' => $contract->contrato_id]);
        $this->post($url, $data + ['aceptacion_cliente' => '1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE']);
        $this->assertDatabaseHas('pedidos_instalacion', [
            'contrato_id' => $contract->contrato_id, 'cliente_id' => $client->getKey(),
            'fecha_programada' => $visit->format('Y-m-d H:i:s'), 'franja_horaria' => '08:00 a 12:00',
            'canal_coordinacion' => 'LLAMADA', 'fase_flujo' => 'PENDIENTE_ASIGNACION',
        ]);
        $this->post($url, $data + ['aceptacion_cliente' => '1'])->assertSessionHasErrors('contrato');
        $this->assertSame(1, DB::table('pedidos_instalacion')->where('contrato_id', $contract->contrato_id)->count());

        Storage::fake('local');
        config()->set('services.installation_drive.folder_id', null);
        $this->mock(ContractSignatureVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()
            ->andReturn(['status' => 'signed']));
        $this->post('/sales/contracts/'.$contract->contrato_id.'/confirm', [
            'documento_pdf' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4\nDocumento firmado"),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'ACTIVO']);
        $this->assertSame(1, DB::table('pedidos_instalacion')->where('contrato_id', $contract->contrato_id)->count());
        $this->assertDatabaseHas('evidencias_drive_instalacion', [
            'tipo' => 'CONTRATO_VALIDADO', 'evidencia_id' => $contract->contrato_id, 'estado' => 'PENDIENTE',
        ]);
    }

    public function test_active_contracts_finalize_after_the_last_day_by_request_or_scheduler(): void
    {
        [$actor, $client, $contract] = $this->records();
        ClientHistoryFixtures::attach($client, $actor, 'RF-AUTO', 4);
        $this->travelTo(Carbon::parse('2026-12-31 23:59:59'));
        $this->signIn($actor)->get('/contracts')->assertOk();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'ACTIVO']);

        $this->travelTo(Carbon::parse('2027-01-01 00:00:00'));
        $this->get('/contracts')->assertOk();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'FINALIZADO']);
        $this->assertDatabaseHas('contratos', ['codigo' => 'RF-AUTO-C-0', 'estado' => 'FINALIZADO']);
        $this->assertDatabaseHas('contratos', ['codigo' => 'RF-AUTO-C-2', 'estado' => 'CANCELADO']);
        $this->assertDatabaseHas('contratos', ['codigo' => 'RF-AUTO-C-3', 'estado' => 'PENDIENTE']);

        DB::table('contratos')->where('codigo', 'RF-AUTO-C-3')->update(['estado' => 'ACTIVO']);
        $this->artisan('contracts:finalize')->expectsOutput('Contratos finalizados por vencimiento: 1')->assertSuccessful();
        $this->artisan('contracts:finalize')->expectsOutput('Contratos finalizados por vencimiento: 0')->assertSuccessful();
        $this->assertDatabaseHas('contratos', ['codigo' => 'RF-AUTO-C-3', 'estado' => 'FINALIZADO']);
        $this->get('/contracts/'.$contract->contrato_id.'/edit')->assertOk()->assertDontSee('value="FINALIZADO"', false);
        $this->put('/contracts/'.$contract->contrato_id, [
            'cliente_id' => $client->getKey(), 'plan_id' => $contract->plan_id,
            'zona_id' => $contract->zona_id, 'responsable_id' => $contract->responsable_id,
            'fecha_inicio' => '2026-01-01', 'duracion_meses' => 12,
            'observaciones' => 'Contrato de prueba RF-CON', 'estado' => 'FINALIZADO',
        ])->assertSessionHasErrors('estado');
    }

    public function test_late_signature_does_not_leave_an_expired_contract_active(): void
    {
        [$actor, $client, $existing] = $this->records();
        $this->travelTo(Carbon::parse('2026-09-18 12:00:00'));
        $data = [
            'cliente_id' => $client->getKey(), 'plan_id' => $existing->plan_id,
            'zona_id' => $existing->zona_id, 'responsable_id' => $existing->responsable_id,
            'fecha_inicio' => '2025-01-01', 'duracion_meses' => 12,
        ];
        $this->signIn($actor)->post('/contracts', $data)->assertSessionHasNoErrors();
        $contract = DB::table('contratos')->orderByDesc('contrato_id')->first();
        $this->assertSame('PENDIENTE', $contract->estado);

        Storage::fake('local');
        $this->mock(ContractSignatureVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()
            ->andReturn(['status' => 'signed']));
        $this->put('/contracts/'.$contract->contrato_id, $data + [
            'documento_pdf' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4\nDocumento firmado"),
        ])->assertSessionHasNoErrors()->assertSessionHas('status_kind', 'finalized');
        $this->assertDatabaseHas('contratos', [
            'contrato_id' => $contract->contrato_id, 'estado' => 'FINALIZADO', 'firma_revision' => 'VERIFICADA',
        ]);
    }
}
