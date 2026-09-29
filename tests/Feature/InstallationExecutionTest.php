<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class InstallationExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $tech;

    private User $manager;

    private int $id;

    private int $other;

    private int $contract;

    private int $material;

    private int $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('America/Lima')->setDate(2026, 9, 26)->setTime(9, 15));
        Storage::fake('local');
        $this->manager = User::factory()->create(['rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')]);
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres_razon_social' => 'Cliente Instalación', 'telefono' => '987654321',
            'direccion' => 'Dirección del cliente', 'creado_por' => $this->manager->getKey()]);
        ClientHistoryFixtures::attach($client, $this->manager, 'INS');
        $record = DB::table('pedidos_instalacion')->where('codigo', 'INS-I-0')->first();
        $this->id = $record->pedido_id;
        $this->contract = $record->contrato_id;
        $this->other = DB::table('pedidos_instalacion')->where('codigo', 'INS-I-3')->value('pedido_id');
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['estado' => 'PROGRAMADO']);
        $this->tech = User::factory()->create(['rol_id' => Role::where('nombre', Role::TECHNICIAN)->value('rol_id'), 'empleado_id' => $record->tecnico_id]);
        $this->material = DB::table('materiales')->insertGetId(['codigo' => 'MAT-INS', 'nombre' => 'Fibra óptica',
            'unidad_medida' => 'm', 'stock_actual' => '10.500', 'creado_por' => $this->manager->getKey()]);
        $this->equipment = DB::table('equipos')->insertGetId(['codigo' => 'EQ-INS', 'nombre' => 'Router',
            'tipo' => 'Router', 'creado_por' => $this->manager->getKey()]);
        $this->signIn($this->tech);
    }

    private function url(string $action = '', ?int $id = null): string
    {
        return '/technician/installations/'.($id ?? $this->id).($action ? '/'.$action : '');
    }

    private function start(): void
    {
        $this->post($this->url('start'))->assertSessionHasNoErrors()->assertRedirect($this->url());
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN9sAAAAASUVORK5CYII='));
    }

    public function test_assignments_search_and_detail_only_show_authorized_installations(): void
    {
        $this->get('/technician')->assertOk()->assertSee('INS-I-0')->assertDontSee('INS-I-3');
        $this->get('/technician?pedido_id='.$this->id)->assertOk()->assertSee('INS-I-0')->assertDontSee('INS-I-1');
        $this->get('/technician?pedido_id='.$this->other)->assertOk()->assertDontSee('INS-I-3');
        $this->get($this->url())->assertOk()->assertSee('Cliente Instalación')->assertSee('987654321')
            ->assertSee('Dirección original del contrato')->assertSee('Internet INS')->assertSee('Iniciar instalación');
        $this->get($this->url('', $this->other))->assertNotFound();
        $this->get('/technician?pedido_id=abc')->assertSessionHasErrors('pedido_id');
    }

    public function test_every_mutation_and_download_is_scoped_to_assignment(): void
    {
        foreach (['start', 'finish', 'equipment', 'material', 'observation', 'evidence', 'contract-evidence'] as $action) {
            $this->post($this->url($action, $this->other))->assertNotFound();
        }
        $this->get($this->url('evidence/1', $this->other))->assertNotFound();
        $this->get($this->url('contract-evidence/1', $this->other))->assertNotFound();
        $this->assertDatabaseCount('ejecuciones_instalacion', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_office_and_guests_cannot_execute_installations(): void
    {
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $this->signIn($office);
        foreach (['start', 'finish', 'equipment', 'material', 'observation', 'evidence', 'contract-evidence'] as $action) {
            $this->post($this->url($action))->assertForbidden();
        }
        auth()->logout();
        $this->post($this->url('start'))->assertRedirect('/login');
    }

    public function test_start_and_finish_record_peru_times_and_are_idempotent(): void
    {
        $this->start();
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'estado' => 'EN_PROCESO', 'inicio_en' => '2026-09-26 09:15:00', 'fin_en' => null]);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'EN_PROCESO']);
        $this->travel(2)->minutes();
        $this->start();
        $this->assertDatabaseCount('ejecuciones_instalacion', 1);
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'inicio_en' => '2026-09-26 09:15:00']);
        $this->post($this->url('finish'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'estado' => 'COMPLETADA', 'fin_en' => '2026-09-26 09:17:00']);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'COMPLETADO']);
        $this->travel(2)->minutes();
        $this->post($this->url('finish'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'fin_en' => '2026-09-26 09:17:00']);
        $this->get($this->url())->assertOk()->assertDontSee('Finalizar instalación')->assertDontSee('Iniciar instalación');
    }

    public function test_invalid_state_transitions_are_blocked(): void
    {
        $this->post($this->url('finish'))->assertSessionHasErrors('instalacion');
        foreach (['COMPLETADO', 'CANCELADO'] as $state) {
            DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['estado' => $state]);
            $this->post($this->url('start'))->assertSessionHasErrors('instalacion');
        }
        $this->assertDatabaseCount('ejecuciones_instalacion', 0);
        $this->signIn($this->manager)->post($this->url('start', $this->other))->assertSessionHasErrors('instalacion');
    }

    public function test_consumption_uses_cumulative_totals_and_kardex_without_duplicate_deductions(): void
    {
        $this->start();
        foreach (['2.125', '2.125', '3.250'] as $total) {
            $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => $total])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('materiales', ['material_id' => $this->material, 'stock_actual' => '7.250']);
        $this->assertDatabaseHas('pedido_materiales', ['pedido_id' => $this->id, 'material_id' => $this->material, 'cantidad_utilizada' => '3.250']);
        $this->assertDatabaseCount('pedido_materiales', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $this->id, 'tipo_movimiento' => 'CONSUMO',
            'cantidad' => '1.125', 'cantidad_anterior' => '8.375', 'cantidad_posterior' => '7.250', 'responsable_id' => $this->tech->getKey()]);
    }

    public function test_insufficient_stock_invalid_quantities_and_stale_totals_leave_stock_unchanged(): void
    {
        $this->start();
        $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => '2'])->assertSessionHasNoErrors();
        foreach (['1', '20', '-1', '0', '0.0001', '1e2', '1000000000', 'abc'] as $total) {
            $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => $total])->assertSessionHasErrors('cantidad_utilizada');
        }
        $this->assertDatabaseHas('materiales', ['material_id' => $this->material, 'stock_actual' => '8.500']);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        DB::table('materiales')->where('material_id', $this->material)->update(['estado' => 'INACTIVO']);
        $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => '3'])->assertSessionHasErrors('material_id');
    }

    public function test_two_installations_cannot_consume_more_than_available_stock(): void
    {
        $this->start();
        $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => '8'])->assertSessionHasNoErrors();
        DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->update(['tecnico_id' => $this->tech->empleado_id]);
        $this->post($this->url('start', $this->other))->assertSessionHasNoErrors();
        $this->post($this->url('material', $this->other), ['material_id' => $this->material, 'cantidad_utilizada' => '3'])->assertSessionHasErrors('cantidad_utilizada');
        $this->assertDatabaseHas('materiales', ['material_id' => $this->material, 'stock_actual' => '2.500']);
        $this->assertDatabaseMissing('pedido_materiales', ['pedido_id' => $this->other]);
    }

    public function test_equipment_is_linked_once_and_cannot_be_reused_by_another_installation(): void
    {
        $this->start();
        for ($i = 0; $i < 2; $i++) {
            $this->post($this->url('equipment'), ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('pedido_equipos', ['pedido_id' => $this->id, 'equipo_id' => $this->equipment, 'estado_asignacion' => 'UTILIZADO']);
        $this->assertDatabaseHas('equipos', ['equipo_id' => $this->equipment, 'estado' => 'ASIGNADO']);
        $this->assertDatabaseCount('pedido_equipos', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->update(['tecnico_id' => $this->tech->empleado_id]);
        $this->post($this->url('start', $this->other))->assertSessionHasNoErrors();
        $this->post($this->url('equipment', $this->other), ['equipo_id' => $this->equipment])->assertSessionHasErrors('equipo_id');
    }

    public function test_preassigned_equipment_can_be_marked_used_and_unavailable_equipment_is_rejected(): void
    {
        $this->start();
        DB::table('equipos')->where('equipo_id', $this->equipment)->update(['estado' => 'MANTENIMIENTO']);
        $this->post($this->url('equipment'), ['equipo_id' => $this->equipment])->assertSessionHasErrors('equipo_id');
        DB::table('equipos')->where('equipo_id', $this->equipment)->update(['estado' => 'ASIGNADO']);
        DB::table('pedido_equipos')->insert(['pedido_id' => $this->id, 'equipo_id' => $this->equipment, 'asignado_por' => $this->manager->getKey()]);
        $this->post($this->url('equipment'), ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_equipos', ['pedido_id' => $this->id, 'estado_asignacion' => 'UTILIZADO']);
    }

    public function test_photographs_and_signed_contracts_are_private_and_linked_to_correct_records(): void
    {
        $this->start();
        $this->post($this->url('evidence'), ['evidence' => $this->photo(), 'descripcion' => 'Foto de instalación'])->assertSessionHasNoErrors();
        $this->post($this->url('contract-evidence'), ['evidence' => $this->photo(), 'descripcion' => 'Contrato firmado'])->assertSessionHasNoErrors();
        $photo = DB::table('evidencias_instalacion')->first();
        $contract = DB::table('evidencias_contrato')->first();
        $this->assertSame($this->id, (int) $photo->pedido_id);
        $this->assertNotNull($photo->ejecucion_id);
        $this->assertSame($this->contract, (int) $contract->contrato_id);
        $this->assertSame('CONTRATO_FIRMADO', $contract->tipo);
        foreach ([$photo, $contract] as $file) {
            Storage::disk('local')->assertExists($file->ruta_archivo);
        }
        $this->get($this->url('evidence/'.$photo->evidencia_id))->assertOk()->assertDownload();
        $this->get($this->url('contract-evidence/'.$contract->evidencia_contrato_id))->assertOk()->assertDownload();
        $this->signIn($this->manager);
        $this->get($this->url('contract-evidence/'.$contract->evidencia_contrato_id, $this->other))->assertNotFound();
        $this->get($this->url('evidence/'.$photo->evidencia_id, $this->other))->assertNotFound();
    }

    public function test_invalid_evidence_does_not_leave_rows_or_files(): void
    {
        $this->start();
        foreach (['evidence', 'contract-evidence'] as $action) {
            $this->post($this->url($action), ['evidence' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertSessionHasErrors('evidence');
            $this->post($this->url($action), ['evidence' => $this->photo()->size(5121)])->assertSessionHasErrors('evidence');
        }
        $this->assertDatabaseCount('evidencias_instalacion', 0);
        $this->assertDatabaseCount('evidencias_contrato', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_pdf_evidence_is_available_from_contract_only_to_office_and_management(): void
    {
        $this->start();
        $pdf = UploadedFile::fake()->createWithContent('contrato.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->post($this->url('contract-evidence'), ['evidence' => $pdf])->assertSessionHasNoErrors();
        $file = DB::table('evidencias_contrato')->first();
        $url = '/contracts/'.$this->contract.'/field-evidence/'.$file->evidencia_contrato_id;
        $this->get($url)->assertForbidden();
        $this->signIn($this->manager)->get('/contracts/'.$this->contract)->assertOk()->assertSee('contrato.pdf');
        $this->get($url)->assertOk()->assertDownload();
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $this->signIn($office)->get($url)->assertOk()->assertDownload();
        $different = DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->value('contrato_id');
        $this->get('/contracts/'.$different.'/field-evidence/'.$file->evidencia_contrato_id)->assertNotFound();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $this->contract, 'pdf_contrato_ruta' => null]);
    }

    public function test_file_storage_failure_is_reported_without_creating_evidence(): void
    {
        $this->start();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('evidencias_instalacion', 0);
    }

    public function test_work_cannot_be_saved_before_start_and_legacy_in_progress_can_register_start(): void
    {
        $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => '1'])->assertSessionHasErrors('instalacion');
        $this->post($this->url('equipment'), ['equipo_id' => $this->equipment])->assertSessionHasErrors('instalacion');
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasErrors('instalacion');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['estado' => 'EN_PROCESO']);
        $this->get($this->url())->assertOk()->assertSee('Iniciar instalación');
        $this->start();
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'inicio_en' => '2026-09-26 09:15:00']);
    }

    public function test_filters_search_client_code_date_and_state_without_leaking_other_assignments(): void
    {
        $this->get('/technician?search=Cliente%20Instalaci%C3%B3n&fecha=2026-09-05&estado=PROGRAMADO')
            ->assertOk()->assertSee('INS-I-0')->assertSee('INS-I-2')->assertDontSee('INS-I-1')->assertDontSee('INS-I-3');
        $this->get('/technician?search=INS-I-0')->assertOk()->assertSee('INS-I-0')->assertDontSee('INS-I-2');
        $this->get('/technician?search=INS-I-3')->assertOk()->assertSee('No hay instalaciones que coincidan')->assertDontSee('Ficha #'.$this->other);
        $this->get('/technician?search=%25')->assertOk()->assertSee('No hay instalaciones que coincidan');
        $this->get('/technician?fecha=2026-02-31')->assertSessionHasErrors('fecha');
        $this->get('/technician?estado=INVALIDO')->assertSessionHasErrors('estado');
        $this->get('/technician')->assertOk()->assertSee('Ejecución de Instalaciones')->assertSee('Dirección original del contrato');
    }

    public function test_list_orders_pending_then_running_then_completed(): void
    {
        $ids = DB::table('pedidos_instalacion')->where('tecnico_id', $this->tech->empleado_id)->orderBy('pedido_id')->pluck('pedido_id');
        foreach (['COMPLETADO', 'EN_PROCESO', 'PROGRAMADO'] as $i => $state) {
            DB::table('pedidos_instalacion')->where('pedido_id', $ids[$i])->update(['estado' => $state]);
        }
        $this->get('/technician')->assertOk()->assertViewHas('records', function ($records) use ($ids) {
            return $records->pluck('pedido_id')->all() === [$ids[2], $ids[1], $ids[0]];
        });
    }

    public function test_photo_previews_are_private_and_scoped_and_reject_non_images(): void
    {
        $this->start();
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasNoErrors();
        $this->post($this->url('contract-evidence'), ['evidence' => $this->photo()])->assertSessionHasNoErrors();
        $photo = DB::table('evidencias_instalacion')->first();
        $contract = DB::table('evidencias_contrato')->first();
        $photoUrl = $this->url('evidence/'.$photo->evidencia_id.'/preview');
        $contractUrl = $this->url('contract-evidence/'.$contract->evidencia_contrato_id.'/preview');
        foreach ([$photoUrl, $contractUrl] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->get($this->url())->assertOk()->assertSee($photoUrl)->assertSee('data-confirm-finish', false)->assertSee('data-evidence-input', false);
        $this->signIn($this->manager)->get($this->url('evidence/'.$photo->evidencia_id.'/preview', $this->other))->assertNotFound();
        $this->get($this->url('contract-evidence/'.$contract->evidencia_contrato_id.'/preview', $this->other))->assertNotFound();
        Storage::disk('local')->put($photo->ruta_archivo, '<html>no es una imagen</html>');
        $this->get($photoUrl)->assertNotFound();
        $otherTech = User::factory()->create(['rol_id' => Role::where('nombre', Role::TECHNICIAN)->value('rol_id')]);
        $this->signIn($otherTech)->get($contractUrl)->assertNotFound();
        $this->get($photoUrl)->assertNotFound();
    }

    public function test_observation_is_escaped_and_completed_installations_are_read_only(): void
    {
        $this->start();
        $this->post($this->url('observation'), ['observacion_tecnica' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
        $this->get($this->url())->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->post($this->url('finish'))->assertSessionHasNoErrors();
        foreach ([
            'material' => ['material_id' => $this->material, 'cantidad_utilizada' => '1'],
            'equipment' => ['equipo_id' => $this->equipment],
            'observation' => ['observacion_tecnica' => 'Cambio'],
            'evidence' => ['evidence' => $this->photo()],
            'contract-evidence' => ['evidence' => $this->photo()],
        ] as $action => $data) {
            $this->post($this->url($action), $data)->assertSessionHasErrors('instalacion');
        }
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }
}
