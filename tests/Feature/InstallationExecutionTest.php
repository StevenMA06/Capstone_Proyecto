<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        config()->set('services.installation_drive', [
            'client_id' => null, 'client_secret' => null, 'refresh_token' => null,
            'folder_id' => null, 'credentials_path' => null,
        ]);
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
            'unidad_base' => 'm', 'stock_actual' => '10.500', 'creado_por' => $this->manager->getKey()]);
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

    private function confirmDriveEvidence(): void
    {
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasNoErrors();
        Storage::disk('local')->put('contracts/signed.pdf', '%PDF-1.4 signed');
        DB::table('contratos')->where('contrato_id', $this->contract)->update([
            'pdf_contrato_ruta' => 'contracts/signed.pdf', 'firma_revision' => 'VERIFICADA',
            'firma_validada_en' => now('America/Lima'), 'estado' => 'ACTIVO',
        ]);
        DB::table('evidencias_drive_instalacion')->where('pedido_id', $this->id)->update([
            'estado' => 'CONFIRMADO', 'drive_file_id' => 'drive-demo-confirmed',
            'confirmado_en' => now('America/Lima'),
        ]);
        DB::table('evidencias_drive_instalacion')->insert([
            'pedido_id' => $this->id, 'tipo' => 'CONTRATO_VALIDADO', 'evidencia_id' => $this->contract,
            'estado' => 'CONFIRMADO', 'drive_file_id' => 'drive-contract-confirmed',
            'source_sha256' => hash_file('sha256', Storage::disk('local')->path('contracts/signed.pdf')),
            'confirmado_en' => now('America/Lima'), 'created_at' => now('America/Lima'), 'updated_at' => now('America/Lima'),
        ]);
        $photo = DB::table('evidencias_instalacion')->where('pedido_id', $this->id)->where('tipo', 'FOTOGRAFIA_INSTALACION')->first();
        DB::table('evidencias_drive_instalacion')->where('pedido_id', $this->id)->where('tipo', 'FOTO')
            ->where('evidencia_id', $photo->evidencia_id)->update([
                'source_sha256' => hash_file('sha256', Storage::disk('local')->path($photo->ruta_archivo)),
            ]);
        app(\App\Services\InstallationCompletionService::class)->completeIfReady($this->id);
    }

    private function requestAndDeliverResources(User $chief): void
    {
        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $this->post('/orders/'.$this->id.'/material', ['material_id' => $this->material, 'cantidad_asignada' => '2'])->assertSessionHasNoErrors();
        $warehouse = User::factory()->create(['rol_id' => Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        foreach (DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->pluck('solicitud_id') as $requestId) {
            $this->signIn($warehouse)->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasNoErrors();
        }
        $this->signIn($chief);
    }

    public function test_order_manager_can_send_a_joint_resource_request_without_being_the_assigned_employee(): void
    {
        $this->signIn($this->manager)->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('Enviar solicitud completa a Almacén');

        $this->post('/orders/'.$this->id.'/resources', [
            'equipo_id' => $this->equipment,
            'materials' => [['material_id' => $this->material, 'cantidad_asignada' => '2']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $group = DB::table('pedido_solicitudes_recursos')->where('pedido_id', $this->id)->first();
        $this->assertNotNull($group);
        $this->assertSame((int) $this->manager->getKey(), (int) $group->solicitado_por);
        $this->assertSame(1, (int) $group->incluye_equipo);
        $this->assertSame(1, (int) $group->incluye_material);
        $this->assertDatabaseCount('pedido_solicitudes_recurso', 2);
    }

    public function test_technician_ficha_does_not_offer_resource_requests(): void
    {
        $this->get($this->url())->assertOk()
            ->assertDontSee('¿Qué necesitas para esta visita?')
            ->assertDontSee('Solicitar equipo y materiales a Almacén');
    }

    public function test_technician_cannot_submit_resource_requests_directly(): void
    {
        $requestsBefore = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->count();
        $permissionId = DB::table('permisos')->where('codigo', 'orders.resources.request')->value('permiso_id');
        DB::table('rol_permiso')->updateOrInsert(
            ['rol_id' => $this->tech->rol_id, 'permiso_id' => $permissionId], ['permitido' => true]
        );
        $this->assertTrue($this->tech->hasPermission('orders.resources.request'));
        $this->assertFalse(app(\App\Services\InstallationResourceRequestService::class)
            ->canRequestForOrder($this->tech, DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->first()));
        $this->post('/orders/'.$this->id.'/equipment', ['equipo_tipo' => 'Router'])->assertForbidden();
        $this->assertSame($requestsBefore, DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->count());
    }

    public function test_order_and_technician_detail_identify_the_people_who_coordinated_and_assigned(): void
    {
        $chiefEmployee = Employee::create([
            'tipo_documento' => 'OTRO', 'numero_documento' => 'CHIEF-INS',
            'nombres' => 'María Elena', 'apellidos' => 'Rojas Salazar', 'cargo' => 'Jefe de Instalación',
        ]);
        $officeEmployee = Employee::create([
            'tipo_documento' => 'OTRO', 'numero_documento' => 'OFFICE-INS',
            'nombres' => 'Ana Lucía', 'apellidos' => 'Pérez Díaz', 'cargo' => 'Asistente de ventas',
        ]);
        $chief = User::factory()->create([
            'rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id'),
            'empleado_id' => $chiefEmployee->getKey(),
        ]);
        $office = User::factory()->create([
            'rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id'),
            'empleado_id' => $officeEmployee->getKey(),
        ]);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'asignado_por' => $chief->getKey(), 'coordinado_por' => $office->getKey(),
        ]);

        $this->signIn($chief)->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('María Elena Rojas Salazar')->assertSee('Ana Lucía Pérez Díaz')
            ->assertSee('Usuario: '.$chief->nombre_usuario)->assertSee('Usuario: '.$office->nombre_usuario);
        $this->signIn($this->tech)->get($this->url())->assertOk()
            ->assertSee('María Elena Rojas Salazar')->assertSee('Ana Lucía Pérez Díaz')
            ->assertSee('Día de la visita')->assertSee('Cliente y servicio')
            ->assertSee('Personas responsables')->assertSee('Seguimiento');
    }

    public function test_dual_role_resource_request_requires_manager_before_same_account_delivers(): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['asignado_por' => $this->manager->getKey()]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $chief->roles()->syncWithoutDetaching([Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);

        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE_GERENCIA', 'solicitado_por' => $chief->getKey()]);
        $this->assertDatabaseMissing('pedido_equipos', ['pedido_id' => $this->id, 'equipo_id' => $this->equipment]);
        $this->get('/warehouse/requests')->assertOk()->assertSee('Pendiente de Gerencia');
        $this->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasErrors('solicitud');
        $chief->roles()->syncWithoutDetaching([Role::where('nombre', Role::MANAGER)->value('rol_id')]);
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/approve')->assertSessionHasErrors('solicitud');

        $this->signIn($this->manager)->get('/orders?recursos=pendiente')->assertOk()
            ->assertSee('Recursos por autorizar')->assertSee('No autorizar esta solicitud');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/approve')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'AUTORIZADA', 'resuelto_por' => $this->manager->getKey()]);
        $this->signIn($chief)->patch('/warehouse/requests/'.$requestId.'/fulfill')
            ->assertSessionHasNoErrors()->assertRedirect(route('warehouse.requests.index', ['vista' => 'historial']).'#solicitud-'.$requestId);
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'ENTREGADA', 'atendido_por' => $chief->getKey()]);
        $this->assertDatabaseHas('pedido_equipos', ['pedido_id' => $this->id, 'equipo_id' => $this->equipment, 'asignado_por' => $chief->getKey()]);
        $this->assertDatabaseHas('movimientos_inventario', ['pedido_id' => $this->id, 'equipo_id' => $this->equipment, 'tipo_movimiento' => 'ASIGNACION', 'responsable_id' => $chief->getKey()]);
        $this->get('/warehouse/requests?vista=historial')->assertOk()->assertSee('Preparada')
            ->assertSee('Recurso preparado y reservado');
        $this->get('/warehouse/requests?vista=historial&buscar=INS-I-0&resultado=ENTREGADA')->assertOk()
            ->assertSee('Solicitud completa preparada y reservada')
            ->assertSee('Preparó y reservó la solicitud completa')
            ->assertDontSee('Autorizó o rechazó');
        $this->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasErrors('solicitud');

        $this->post('/orders/'.$this->id.'/material', ['material_id' => $this->material, 'cantidad_asignada' => '1'])->assertSessionHasNoErrors();
        $materialRequest = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->where('tipo', 'MATERIAL')->value('solicitud_id');
        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/resources/'.$materialRequest.'/approve')->assertSessionHasNoErrors();
        $this->signIn($chief)->get('/warehouse/requests')->assertOk()->assertDontSee('El stock disponible no alcanza');
        $this->patch('/warehouse/requests/'.$materialRequest.'/reject', [
            'decision_motivo_codigo' => 'STOCK_INSUFICIENTE',
        ])->assertSessionHasErrors('decision_motivo_codigo');
        $this->patch('/warehouse/requests/'.$materialRequest.'/reject', [
            'decision_motivo_codigo' => 'OTRO',
        ])->assertSessionHasErrors('decision_observacion');
        $this->patch('/warehouse/requests/'.$materialRequest.'/reject', [
            'decision_motivo_codigo' => 'RECURSO_NO_APTO', 'decision_observacion' => 'Material no apto',
        ])
            ->assertSessionHasNoErrors()->assertRedirect(route('warehouse.requests.index', ['vista' => 'historial']).'#solicitud-'.$materialRequest);
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $materialRequest, 'estado' => 'RECHAZADA',
            'resuelto_por' => $this->manager->getKey(), 'cerrado_por' => $chief->getKey(),
            'decision_motivo_codigo' => 'RECURSO_NO_APTO', 'decision_observacion' => 'Material no apto']);
        $this->assertDatabaseMissing('pedido_materiales', ['pedido_id' => $this->id, 'material_id' => $this->material]);
        $this->get('/warehouse/requests?vista=historial')->assertOk()->assertSee('Motivo registrado:')
            ->assertSee('El recurso no está apto para instalarse')->assertSee('Material no apto');
        $this->get('/warehouse/requests?vista=historial&buscar=INS-I-0&resultado=RECHAZADA')->assertOk()
            ->assertSee('Rechazó la solicitud completa')
            ->assertDontSee('Preparó y reservó la solicitud completa');
    }

    public function test_equipment_type_can_be_requested_with_no_free_unit_and_warehouse_selects_the_delivered_unit(): void
    {
        DB::table('equipos')->where('equipo_id', $this->equipment)->update(['estado' => 'ASIGNADO']);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);

        $this->signIn($chief)->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('value="Router" data-available="0"', false);
        $this->post('/orders/'.$this->id.'/equipment', ['equipo_tipo' => 'Router'])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->where('tipo', 'EQUIPO')->value('solicitud_id');
        $this->assertDatabaseHas('pedido_solicitudes_recurso', [
            'solicitud_id' => $requestId, 'equipo_tipo_solicitado' => 'Router',
            'equipo_id' => null, 'estado' => 'PENDIENTE_ALMACEN',
        ]);
        $this->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('Equipo tipo Router')->assertSee('Solicita el material a Almacén');
        $this->post('/orders/'.$this->id.'/equipment', ['equipo_tipo' => 'Router'])->assertSessionHasErrors('equipo_tipo');

        $warehouse = User::factory()->create(['rol_id' => Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->signIn($warehouse)->get('/warehouse/requests')->assertOk()
            ->assertSee('Equipo tipo Router')->assertSee('No hay unidades libres de este tipo');
        $this->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasErrors('equipo_id');

        $switch = DB::table('equipos')->insertGetId([
            'codigo' => 'EQ-SWITCH-INS', 'nombre' => 'Switch', 'tipo' => 'Switch', 'creado_por' => $this->manager->getKey(),
        ]);
        $router = DB::table('equipos')->insertGetId([
            'codigo' => 'EQ-ROUTER-INS', 'nombre' => 'Router nuevo', 'tipo' => 'Router', 'creado_por' => $this->manager->getKey(),
        ]);
        $this->patch('/warehouse/requests/'.$requestId.'/fulfill', ['equipo_id' => $switch])->assertSessionHasErrors('equipo_id');
        $this->get('/warehouse/requests')->assertOk()->assertSee('EQ-ROUTER-INS');
        $this->patch('/warehouse/requests/'.$requestId.'/fulfill', ['equipo_id' => $router])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', [
            'solicitud_id' => $requestId, 'equipo_id' => $router, 'estado' => 'ENTREGADA',
        ]);
        $this->assertDatabaseHas('pedido_equipos', ['pedido_id' => $this->id, 'equipo_id' => $router]);
    }

    public function test_technician_can_only_be_assigned_after_warehouse_prepares_equipment_and_material(): void
    {
        DB::table('empleados')->where('empleado_id', $this->tech->empleado_id)->update(['cargo' => 'Técnico de Campo']);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'estado' => 'PENDIENTE', 'fase_flujo' => 'PENDIENTE_ASIGNACION',
            'tecnico_id' => null, 'asignado_por' => null, 'coordinado_en' => now('America/Lima'),
            'fecha_programada' => now('America/Lima')->addDay(), 'franja_horaria' => '08:00 a 12:00',
        ]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $warehouse = User::factory()->create(['rol_id' => Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->signIn($chief)->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('2 · Solicita equipo y material a Almacén')
            ->assertSee('Solicitar recursos a Almacén')
            ->assertDontSee('3. Asignar técnico');
        $this->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])
            ->assertSessionHasErrors('pedido');
        $this->post('/orders/'.$this->id.'/equipment', ['equipo_tipo' => 'Router'])->assertSessionHasNoErrors();
        $this->post('/orders/'.$this->id.'/material', ['material_id' => $this->material, 'cantidad_asignada' => '2'])->assertSessionHasNoErrors();
        $requests = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->pluck('solicitud_id', 'tipo');
        $this->signIn($warehouse)->patch('/warehouse/requests/'.$requests['EQUIPO'].'/fulfill', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $this->signIn($chief)->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])
            ->assertSessionHasErrors('pedido');
        $this->signIn($warehouse)->patch('/warehouse/requests/'.$requests['MATERIAL'].'/fulfill')->assertSessionHasNoErrors();
        $this->signIn($chief)->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('3 · Asigna al técnico instalador')->assertSee('3. Asignar técnico');
        $this->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'PROGRAMADO',
            'tecnico_id' => $this->tech->empleado_id, 'asignado_por' => $chief->getKey()]);
    }

    public function test_same_employee_cannot_be_assigned_as_primary_and_secondary_technician(): void
    {
        DB::table('empleados')->where('empleado_id', $this->tech->empleado_id)->update(['cargo' => 'Técnico de Campo']);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'estado' => 'PENDIENTE', 'fase_flujo' => 'PENDIENTE_ASIGNACION',
            'tecnico_id' => null, 'tecnico_secundario_id' => null, 'asignado_por' => null,
            'coordinado_en' => now('America/Lima'), 'fecha_programada' => now('America/Lima')->addDay(),
            'franja_horaria' => '08:00 a 12:00',
        ]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);

        $this->signIn($chief)->patch('/orders/'.$this->id.'/schedule', [
            'tecnico_id' => $this->tech->empleado_id,
            'tecnico_secundario_id' => $this->tech->empleado_id,
        ])->assertSessionHasErrors([
            'tecnico_secundario_id' => 'El técnico principal y el segundo técnico deben ser personas distintas.',
        ]);

        $this->assertDatabaseHas('pedidos_instalacion', [
            'pedido_id' => $this->id, 'tecnico_id' => null, 'tecnico_secundario_id' => null,
        ]);
    }

    public function test_technician_cannot_be_assigned_to_two_installations_in_the_same_hour_but_can_work_another_hour_that_day(): void
    {
        DB::table('empleados')->where('empleado_id', $this->tech->empleado_id)->update(['cargo' => 'Técnico de Campo']);
        $visitTime = now('America/Lima')->addDays(2)->setTime(10, 20);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'estado' => 'PENDIENTE', 'fase_flujo' => 'PENDIENTE_ASIGNACION', 'tecnico_id' => null,
            'coordinado_en' => now('America/Lima'), 'fecha_programada' => $visitTime->format('Y-m-d H:i:s'),
            'franja_horaria' => '08:00 a 12:00',
        ]);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->update([
            'estado' => 'PROGRAMADO', 'tecnico_id' => $this->tech->empleado_id,
            'fecha_programada' => $visitTime->copy()->setTime(10, 50)->format('Y-m-d H:i:s'),
        ]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $this->requestAndDeliverResources($chief);
        Mail::fake();

        $this->signIn($chief)->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])
            ->assertSessionHasErrors('tecnico_id');
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'tecnico_id' => null]);

        DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->update([
            'fecha_programada' => $visitTime->copy()->setTime(11, 10)->format('Y-m-d H:i:s'),
        ]);
        $this->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', [
            'pedido_id' => $this->id, 'tecnico_id' => $this->tech->empleado_id, 'estado' => 'PROGRAMADO',
        ]);
    }

    public function test_one_submission_records_equipment_and_multiple_materials_as_one_request_group(): void
    {
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $secondMaterial = DB::table('materiales')->insertGetId(['codigo' => 'MAT-INS-2', 'nombre' => 'Conectores',
            'unidad_base' => 'unidades', 'unidad_empaque' => 'caja', 'factor_conversion' => '12.000',
            'inventario_en_empaque' => true, 'stock_actual' => '25.000', 'creado_por' => $this->manager->getKey()]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/resources', [
            'materials' => [['material_id' => $secondMaterial, 'cantidad_asignada' => '1.5']],
        ])->assertSessionHasErrors('cantidad_asignada');
        $this->signIn($chief)->post('/orders/'.$this->id.'/resources', [
            'equipo_id' => $this->equipment,
            'materials' => [
                ['material_id' => $this->material, 'cantidad_asignada' => '2'],
                ['material_id' => $secondMaterial, 'cantidad_asignada' => '5'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $requests = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->get();
        $this->assertCount(3, $requests);
        $this->assertEqualsCanonicalizing(['EQUIPO', 'MATERIAL', 'MATERIAL'], $requests->pluck('tipo')->all());
        $this->assertCount(1, $requests->pluck('solicitud_grupo_id')->unique());
        $this->get('/orders?search=INS-I-0')->assertOk()
            ->assertSee('Pendiente de Almacén')
            ->assertSee('Router')
            ->assertSee('Fibra óptica')
            ->assertSee('Conectores')
            ->assertSee('5 caja')
            ->assertSee('60 unidades en total')
            ->assertSee('Solicitud conjunta · 3 recursos')
            ->assertSee('Ver estado de los recursos solicitados')
            ->assertDontSee('Enviar solicitud a Almacén')
            ->assertDontSee('Registrar pedido de un contrato activo anterior');

        $switch = DB::table('equipos')->insertGetId([
            'codigo' => 'EQ-EXTRA-INS', 'nombre' => 'Switch', 'tipo' => 'Switch',
            'creado_por' => $this->manager->getKey(),
        ]);
        $this->post('/orders/'.$this->id.'/resources', [
            'materials' => [
                ['material_id' => $this->material, 'cantidad_asignada' => '1'],
                ['material_id' => $this->material, 'cantidad_asignada' => '1'],
            ],
        ])->assertSessionHasErrors('materials.1.material_id');
        $this->assertDatabaseMissing('pedido_solicitudes_recurso', [
            'pedido_id' => $this->id, 'material_id' => $secondMaterial,
        ]);
        $this->assertDatabaseHas('equipos', ['equipo_id' => $switch, 'estado' => 'DISPONIBLE']);
    }

    public function test_separate_warehouse_account_can_deliver_material_and_cancellation_closes_pending_requests(): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['asignado_por' => $this->manager->getKey()]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $warehouse = User::factory()->create(['rol_id' => Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/material', ['material_id' => $this->material, 'cantidad_asignada' => '2'])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE_ALMACEN']);
        $this->assertDatabaseMissing('pedido_materiales', ['pedido_id' => $this->id, 'material_id' => $this->material]);
        $this->signIn($warehouse)->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_materiales', ['pedido_id' => $this->id, 'material_id' => $this->material, 'cantidad_asignada' => '2.000', 'asignado_por' => $warehouse->getKey()]);

        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $pendingId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->where('tipo', 'EQUIPO')->value('solicitud_id');
        $this->patch('/orders/'.$this->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $pendingId, 'estado' => 'ANULADA']);
        $this->signIn($warehouse)->patch('/warehouse/requests/'.$pendingId.'/fulfill')->assertSessionHasErrors('solicitud');
    }

    public function test_new_warehouse_role_can_escalate_an_existing_self_request(): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['asignado_por' => $this->manager->getKey()]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');
        $chief->roles()->syncWithoutDetaching([Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasErrors('solicitud');
        $this->get('/warehouse/requests')->assertOk()->assertSee('Enviar a Gerencia');
        $this->patch('/warehouse/requests/'.$requestId.'/escalate')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE_GERENCIA']);
        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/resources/'.$requestId.'/approve')->assertSessionHasNoErrors();
        $this->signIn($chief)->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasNoErrors();
    }

    public function test_warehouse_permission_can_escalate_without_warehouse_role(): void
    {
        $chiefRole = Role::where('nombre', Role::INSTALLATIONS)->firstOrFail();
        $chief = User::factory()->create(['rol_id' => $chiefRole->getKey()]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE_ALMACEN']);

        foreach (['warehouse.view', 'warehouse.fulfill'] as $code) {
            DB::table('rol_permiso')->updateOrInsert([
                'rol_id' => $chiefRole->getKey(),
                'permiso_id' => DB::table('permisos')->where('codigo', $code)->value('permiso_id'),
            ], ['permitido' => true]);
        }
        $this->assertFalse($chief->hasRole(Role::WAREHOUSE));
        $this->get('/warehouse/requests')->assertOk()->assertSee('Enviar a Gerencia para revisión');
        $this->patch('/warehouse/requests/'.$requestId.'/escalate')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE_GERENCIA']);

        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/resources/'.$requestId.'/approve')->assertSessionHasNoErrors();
        $this->signIn($chief)->patch('/warehouse/requests/'.$requestId.'/fulfill')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'ENTREGADA']);

        $this->post('/orders/'.$this->id.'/material', ['material_id' => $this->material, 'cantidad_asignada' => '1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['pedido_id' => $this->id, 'tipo' => 'MATERIAL', 'estado' => 'PENDIENTE_GERENCIA']);
    }

    public function test_resource_approval_permission_shows_queue_without_manager_role(): void
    {
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $chief->roles()->syncWithoutDetaching([Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');
        $this->assertDatabaseHas('pedido_solicitudes_recurso', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE_GERENCIA']);

        $reviewRole = Role::where('nombre', Role::IT)->firstOrFail();
        foreach (['orders.view', 'orders.resources.approve'] as $code) {
            DB::table('rol_permiso')->updateOrInsert([
                'rol_id' => $reviewRole->getKey(),
                'permiso_id' => DB::table('permisos')->where('codigo', $code)->value('permiso_id'),
            ], ['permitido' => true]);
        }
        $reviewer = User::factory()->create(['rol_id' => $reviewRole->getKey()]);
        $this->assertFalse($reviewer->hasRole(Role::MANAGER));
        $this->signIn($reviewer)->get('/orders?recursos=pendiente')->assertOk()
            ->assertSee('Recursos por autorizar')->assertSee('Autorizar preparación');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/approve')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', [
            'solicitud_id' => $requestId, 'estado' => 'AUTORIZADA', 'resuelto_por' => $reviewer->getKey(),
        ]);
    }

    public function test_management_resource_review_requires_a_reason_to_reject(): void
    {
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $chief->roles()->syncWithoutDetaching([Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');

        $this->signIn($this->manager)->get('/orders?recursos=pendiente')->assertOk()
            ->assertSee('DECISIÓN DE GERENCIA')->assertSee('Fecha de solicitud')
            ->assertSee('Autorizar preparación en Almacén')->assertSee('No autorizar esta solicitud')
            ->assertDontSee('La solicitud está duplicada')->assertDontSee('Faltan datos para autorizar')
            ->assertDontSee('La cantidad de material no está justificada');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/reject', [])->assertSessionHasErrors('decision_motivo_codigo');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/reject', [
            'decision_motivo_codigo' => 'OTRO',
        ])->assertSessionHasErrors('decision_observacion');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/reject', [
            'decision_motivo_codigo' => 'OTRO', 'decision_observacion' => '   ',
        ])->assertSessionHasErrors('decision_observacion');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/reject', [
            'decision_motivo_codigo' => 'SIN_DISPONIBILIDAD',
        ])->assertSessionHasErrors('decision_motivo_codigo');
        $this->patch('/orders/'.$this->id.'/resources/'.$requestId.'/reject', [
            'decision_motivo_codigo' => 'RECURSO_NO_CORRESPONDE',
            'decision_observacion' => 'La unidad solicitada no es apropiada para esta visita.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_solicitudes_recurso', [
            'solicitud_id' => $requestId, 'estado' => 'RECHAZADA',
            'decision_motivo_codigo' => 'RECURSO_NO_CORRESPONDE',
            'decision_observacion' => 'La unidad solicitada no es apropiada para esta visita.',
            'resuelto_por' => $this->manager->getKey(),
        ]);
        $this->get('/orders?search=INS-I-0')->assertOk()->assertSee('El recurso no corresponde a la instalación');
    }

    public function test_management_can_reject_for_other_reason_with_required_explanation(): void
    {
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $chief->roles()->syncWithoutDetaching([Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
        $this->signIn($chief)->post('/orders/'.$this->id.'/equipment', ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $requestId = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $this->id)->value('solicitud_id');

        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/resources/'.$requestId.'/reject', [
            'decision_motivo_codigo' => 'OTRO',
            'decision_observacion' => 'El equipo solicitado requiere una revisión especial antes de autorizarlo.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pedido_solicitudes_recurso', [
            'solicitud_id' => $requestId,
            'estado' => 'RECHAZADA',
            'decision_motivo_codigo' => 'OTRO',
            'decision_observacion' => 'El equipo solicitado requiere una revisión especial antes de autorizarlo.',
        ]);
        $this->get('/orders?search=INS-I-0')->assertOk()->assertSee('Otro motivo')
            ->assertSee('El equipo solicitado requiere una revisión especial antes de autorizarlo.');
    }

    public function test_new_assignment_requires_technician_confirmation_and_records_structured_incident(): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'fase_flujo' => 'ESPERANDO_CONFIRMACION_TECNICO',
            'coordinado_en' => now('America/Lima'),
            'franja_horaria' => '08:00 a 12:00',
            'asignado_por' => $this->manager->getKey(),
        ]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $this->requestAndDeliverResources($chief);
        $this->signIn($this->tech);
        $this->post($this->url('start'))->assertSessionHasErrors('instalacion');
        $this->post($this->url('confirm'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'fase_flujo' => 'CONFIRMADO']);
        $this->start();
        $this->post($this->url('equipment'), ['equipo_id' => $this->equipment])->assertSessionHasNoErrors();
        $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => '3'])->assertSessionHasErrors('cantidad_utilizada');
        $this->post($this->url('material'), ['material_id' => $this->material, 'cantidad_utilizada' => '1'])->assertSessionHasNoErrors();
        $this->post($this->url('incident'), [
            'tipo' => 'AVERIA', 'recurso_tipo' => 'EQUIPO', 'recurso_id' => $this->equipment,
            'recurso_descripcion' => 'Router de prueba', 'cantidad' => '1',
            'causa' => 'Golpe accidental', 'observacion' => 'Equipo dejó de encender',
            'evidencia' => $this->photo(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incidencias_instalacion', [
            'pedido_id' => $this->id, 'tipo' => 'AVERIA', 'recurso_id' => $this->equipment,
        ]);
        $this->get($this->url())->assertOk()->assertSee('Golpe accidental');
    }

    public function test_technician_email_uses_configured_public_url_from_local_request(): void
    {
        config(['app.url' => 'https://tunnel.example.test/', 'mail.default' => 'array']);
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $transport->flush();
        DB::table('empleados')->where('empleado_id', $this->tech->empleado_id)->update(['cargo' => 'Técnico de Campo']);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'estado' => 'PENDIENTE', 'fase_flujo' => 'PENDIENTE_ASIGNACION',
            'tecnico_id' => null, 'coordinado_en' => now('America/Lima'),
            'fecha_programada' => now('America/Lima')->addDay(), 'franja_horaria' => '08:00 a 12:00',
        ]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $this->requestAndDeliverResources($chief);

        $this->signIn($chief)->withServerVariables(['HTTP_HOST' => '127.0.0.1:8000'])
            ->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])
            ->assertSessionHasNoErrors();

        $message = $transport->messages()->last()?->getOriginalMessage();
        $this->assertNotNull($message);
        $this->assertStringContainsString('NUEVA ASIGNACIÓN', $message->getTextBody());
        $this->assertStringContainsString('Te asignaron una instalación', $message->getTextBody());
        $this->assertStringContainsString('https://tunnel.example.test/login', $message->getTextBody());
        $this->assertStringContainsString('https://tunnel.example.test/technician/installations/'.$this->id, $message->getTextBody());
        $this->assertStringContainsString('Iniciar sesión en Lunnys', $message->getHtmlBody());
        $this->assertStringNotContainsString('127.0.0.1', $message->getTextBody());
    }

    public function test_technician_mail_command_sends_link_without_changing_order(): void
    {
        config([
            'app.url' => 'https://tunnel.example.test/',
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'array'],
        ]);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $transport->flush();
        $before = DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->first();

        $this->artisan('installations:mail-test', ['pedido' => $this->id, '--preview' => true])
            ->assertExitCode(0);
        $this->assertCount(0, $transport->messages());
        $this->artisan('installations:mail-test', ['pedido' => $this->id])->assertExitCode(0);

        $message = $transport->messages()->last()?->getOriginalMessage();
        $this->assertNotNull($message);
        $this->assertStringContainsString('CORREO DE PRUEBA', $message->getTextBody());
        $this->assertStringContainsString('https://tunnel.example.test/login', $message->getTextBody());
        $this->assertStringContainsString('https://tunnel.example.test/technician/installations/'.$this->id, $message->getTextBody());
        $this->assertSame([$this->tech->correo], array_map(fn ($address) => $address->getAddress(), $message->getTo()));
        $after = DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->first();
        $this->assertSame($before->estado, $after->estado);
        $this->assertSame($before->notificado_en, $after->notificado_en);
    }

    public function test_mobile_evidence_upload_returns_to_its_section_and_explains_unconfigured_drive(): void
    {
        config()->set('services.installation_drive.folder_id', null);
        $this->start();

        $this->post($this->url('evidence'), ['evidence' => $this->photo()])
            ->assertSessionHasNoErrors()->assertRedirect($this->url().'#instalacion-fotos');
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])
            ->assertSessionHasNoErrors()->assertRedirect($this->url().'#instalacion-fotos');

        $this->get($this->url())->assertOk()
            ->assertSee('La carpeta de Drive aún no está vinculada.')
            ->assertSee('Falta confirmar una foto de la instalación en Drive')
            ->assertDontSee('Reintentar envío a Drive');
        $this->assertDatabaseHas('evidencias_drive_instalacion', [
            'pedido_id' => $this->id, 'tipo' => 'FOTO', 'estado' => 'PENDIENTE',
        ]);
        $this->assertDatabaseCount('evidencias_drive_instalacion', 2);

        config()->set('services.installation_drive.folder_id', 'demo-folder');
        config()->set('services.installation_drive.client_id', 'demo-client');
        config()->set('services.installation_drive.client_secret', 'demo-secret');
        config()->set('services.installation_drive.refresh_token', 'demo-refresh');
        $this->get($this->url())->assertOk()->assertSee('Reintentar envío a Drive');
    }

    public function test_pending_order_reprogramming_updates_date_and_state_before_assignment(): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'tecnico_id' => null, 'fecha_programada' => null, 'fecha_original' => null,
            'estado' => 'PENDIENTE', 'fase_flujo' => 'PENDIENTE_COORDINACION',
            'coordinado_en' => null, 'franja_horaria' => null,
        ]);
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $firstDate = now('America/Lima')->addDay()->setTime(9, 0)->format('Y-m-d H:i');
        $newDate = now('America/Lima')->addDays(2)->setTime(14, 0)->format('Y-m-d H:i');
        $this->signIn($office)->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => $firstDate, 'franja_horaria' => '08:00 a 12:00',
            'canal_coordinacion' => 'LLAMADA',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'PENDIENTE']);
        $this->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => $firstDate, 'franja_horaria' => '08:00 a 12:00',
            'canal_coordinacion' => 'LLAMADA', 'motivo_codigo' => 'SOLICITUD_CLIENTE',
            'motivo_reprogramacion' => 'Cliente pidió cambio', 'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasErrors('fecha_programada');
        Mail::fake();
        $this->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => $newDate, 'franja_horaria' => '14:00 a 18:00',
            'canal_coordinacion' => 'WHATSAPP', 'motivo_codigo' => 'SOLICITUD_CLIENTE',
            'motivo_reprogramacion' => 'Cliente pidió cambio',
        ])->assertSessionHasErrors('evidencia_acuerdo');
        $this->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => $newDate, 'franja_horaria' => '14:00 a 18:00',
            'canal_coordinacion' => 'WHATSAPP', 'motivo_codigo' => 'SOLICITUD_CLIENTE',
            'motivo_reprogramacion' => 'Cliente pidió cambio', 'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Solicitud enviada a Gerencia. La fecha actual se mantiene y el aviso al técnico se enviará solo si Gerencia aprueba.');
        $requestId = DB::table('pedido_reprogramaciones')->where('pedido_id', $this->id)->value('reprogramacion_id');
        $this->assertDatabaseHas('pedidos_instalacion', [
            'pedido_id' => $this->id, 'estado' => 'PENDIENTE', 'fecha_programada' => $firstDate.':00',
        ]);
        $this->assertDatabaseHas('pedido_reprogramaciones', [
            'reprogramacion_id' => $requestId, 'estado' => 'SOLICITADA',
            'fecha_anterior' => $firstDate.':00', 'fecha_nueva' => $newDate.':00',
            'motivo_codigo' => 'SOLICITUD_CLIENTE',
        ]);
        $this->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => now('America/Lima')->addDays(3)->setTime(14, 0)->format('Y-m-d H:i'),
            'franja_horaria' => '14:00 a 18:00', 'canal_coordinacion' => 'WHATSAPP',
            'motivo_codigo' => 'SOLICITUD_CLIENTE', 'motivo_reprogramacion' => 'Segundo cambio',
            'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasErrors('pedido');
        $this->patch('/orders/'.$this->id.'/reprogramming/'.$requestId.'/approve')->assertForbidden();
        $this->signIn($this->manager)->get('/orders?reprogramacion=pendiente')->assertOk()
            ->assertSee('Aprobar cambio de fecha')->assertSee('Cliente pidió cambio')
            ->assertSee('Archivo para sustentar el acuerdo')->assertSee('Visita vigente')->assertSee('Fecha solicitada');
        $this->get('/orders/'.$this->id.'/reprogramming/'.$requestId.'/evidence')->assertOk();
        $this->patch('/orders/'.$this->id.'/reprogramming/'.$requestId.'/approve', ['evidencia_revisada' => '1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', [
            'pedido_id' => $this->id, 'estado' => 'REPROGRAMADO',
            'fecha_programada' => $newDate.':00', 'fecha_original' => $firstDate.':00',
            'motivo_reprogramacion' => 'Cliente pidió cambio',
        ]);
        $this->assertDatabaseHas('pedido_reprogramaciones', [
            'reprogramacion_id' => $requestId, 'estado' => 'APROBADA', 'resuelto_por' => $this->manager->getKey(),
        ]);
        $this->get('/orders?estado=REPROGRAMADO')->assertOk()->assertSee('INS-I-0')->assertDontSee('Solicitar cambio de fecha');
        $this->get('/orders?search=INS-I-0')->assertOk()->assertSee('Historial de reprogramaciones');

        DB::table('empleados')->where('empleado_id', $this->tech->empleado_id)->update(['cargo' => 'Técnico de Campo']);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        Mail::fake();
        $this->requestAndDeliverResources($chief);
        $this->signIn($chief)->patch('/orders/'.$this->id.'/schedule', ['tecnico_id' => $this->tech->empleado_id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', [
            'pedido_id' => $this->id, 'estado' => 'REPROGRAMADO',
            'fase_flujo' => 'ESPERANDO_CONFIRMACION_TECNICO',
            'motivo_reprogramacion' => 'Cliente pidió cambio',
        ]);
        $this->get('/technician?pedido_id='.$this->id)->assertOk()
            ->assertSee('El técnico aún debe confirmar que recibió la asignación')
            ->assertDontSee('Solicitar cambio de fecha');
        $this->signIn($this->tech)->get('/technician')->assertOk()->assertSee('Esta visita fue reprogramada');
    }

    public function test_management_decisions_keep_a_complete_history_and_cannot_be_self_approved(): void
    {
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $original = DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->value('fecha_programada');
        $first = now('America/Lima')->addDays(5)->setTime(9, 0)->format('Y-m-d H:i');
        $second = now('America/Lima')->addDays(6)->setTime(10, 0)->format('Y-m-d H:i');
        config(['app.url' => 'https://lunnys.codryon.com', 'mail.default' => 'array']);
        $mailTransport = Mail::mailer('array')->getSymfonyTransport();
        $mailTransport->flush();
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['notificado_en' => null]);
        $this->signIn($office)->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => $first, 'franja_horaria' => '08:00 a 12:00',
            'canal_coordinacion' => 'LLAMADA', 'motivo_codigo' => 'SOLICITUD_CLIENTE',
            'motivo_reprogramacion' => 'Cliente solicitó horario diferente', 'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasNoErrors();
        $firstId = DB::table('pedido_reprogramaciones')->where('pedido_id', $this->id)->value('reprogramacion_id');
        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/reprogramming/'.$firstId.'/reject', [
            'decision_observacion' => 'La fecha aún no fue confirmada por el cliente.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'fecha_programada' => $original]);
        $this->assertDatabaseHas('pedido_reprogramaciones', [
            'reprogramacion_id' => $firstId, 'estado' => 'RECHAZADA', 'resuelto_por' => $this->manager->getKey(),
        ]);

        $this->signIn($office)->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => $second, 'franja_horaria' => '08:00 a 12:00',
            'canal_coordinacion' => 'CORREO', 'motivo_codigo' => 'IMPREVISTO',
            'motivo_reprogramacion' => 'Nueva fecha confirmada por correo.', 'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasNoErrors();
        $secondId = DB::table('pedido_reprogramaciones')->where('pedido_id', $this->id)
            ->orderByDesc('reprogramacion_id')->value('reprogramacion_id');
        $this->assertNull(DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->value('notificado_en'));
        $this->signIn($this->tech)->post($this->url('start'))->assertSessionHasErrors('instalacion');
        DB::table('empleados')->where('empleado_id', $this->tech->empleado_id)->update(['cargo' => 'Técnico de Campo']);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->update([
            'estado' => 'PROGRAMADO', 'tecnico_id' => $this->tech->empleado_id,
            'fecha_programada' => $second.':50',
        ]);
        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/reprogramming/'.$secondId.'/approve', ['evidencia_revisada' => '1'])
            ->assertSessionHasErrors('reprogramacion');
        $this->assertDatabaseHas('pedido_reprogramaciones', ['reprogramacion_id' => $secondId, 'estado' => 'SOLICITADA']);
        DB::table('pedidos_instalacion')->where('pedido_id', $this->other)->update([
            'fecha_programada' => \Illuminate\Support\Carbon::parse($second)->setTime(11, 10)->format('Y-m-d H:i:s'),
        ]);
        $this->signIn($this->manager)->patch('/orders/'.$this->id.'/reprogramming/'.$secondId.'/approve', ['evidencia_revisada' => '1'])->assertSessionHasNoErrors();
        $reprogrammingMessage = $mailTransport->messages()->last()?->getOriginalMessage();
        $this->assertNotNull($reprogrammingMessage);
        $this->assertStringContainsString('VISITA REPROGRAMADA', $reprogrammingMessage->getTextBody());
        $this->assertStringContainsString('Se reprogramó tu visita', $reprogrammingMessage->getTextBody());
        $this->assertStringContainsString('https://lunnys.codryon.com/login', $reprogrammingMessage->getTextBody());
        $this->assertDatabaseCount('pedido_reprogramaciones', 2);
        $this->assertDatabaseHas('pedido_reprogramaciones', ['reprogramacion_id' => $firstId, 'estado' => 'RECHAZADA']);
        $this->assertDatabaseHas('pedido_reprogramaciones', ['reprogramacion_id' => $secondId, 'estado' => 'APROBADA']);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'fecha_programada' => $second.':00']);
        $this->assertNotNull(DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->value('notificado_en'));

        $this->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => now('America/Lima')->addDays(7)->setTime(11, 0)->format('Y-m-d H:i'),
            'franja_horaria' => '08:00 a 12:00', 'canal_coordinacion' => 'LLAMADA',
            'motivo_codigo' => 'OTRO', 'motivo_reprogramacion' => 'Gerencia propone otro horario.',
            'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasNoErrors();
        $thirdId = DB::table('pedido_reprogramaciones')->where('pedido_id', $this->id)
            ->orderByDesc('reprogramacion_id')->value('reprogramacion_id');
        $this->patch('/orders/'.$this->id.'/reprogramming/'.$thirdId.'/approve', ['evidencia_revisada' => '1'])->assertSessionHasErrors('reprogramacion');
        $this->assertDatabaseHas('pedido_reprogramaciones', ['reprogramacion_id' => $thirdId, 'estado' => 'SOLICITADA']);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'fecha_programada' => $second.':00']);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $this->signIn($chief)->patch('/orders/'.$this->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedido_reprogramaciones', ['reprogramacion_id' => $thirdId, 'estado' => 'ANULADA']);
    }

    public function test_changed_evidence_blocks_management_approval(): void
    {
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $original = DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->value('fecha_programada');
        $this->signIn($office)->patch('/dashboard/call-center/visitas/'.$this->id, [
            'fecha_programada' => now('America/Lima')->addDays(4)->setTime(9, 0)->format('Y-m-d H:i'),
            'franja_horaria' => '08:00 a 12:00', 'canal_coordinacion' => 'WHATSAPP',
            'motivo_codigo' => 'SOLICITUD_CLIENTE', 'evidencia_acuerdo' => $this->photo(),
        ])->assertSessionHasNoErrors();
        $change = DB::table('pedido_reprogramaciones')->where('pedido_id', $this->id)->first();
        Storage::disk('local')->put($change->evidencia_ruta, 'contenido alterado');
        $this->signIn($this->manager)->get('/orders/'.$this->id.'/reprogramming/'.$change->reprogramacion_id.'/evidence')->assertStatus(409);
        $this->patch('/orders/'.$this->id.'/reprogramming/'.$change->reprogramacion_id.'/approve', [
            'evidencia_revisada' => '1',
        ])->assertSessionHasErrors('evidencia_acuerdo');
        $this->assertDatabaseHas('pedido_reprogramaciones', ['reprogramacion_id' => $change->reprogramacion_id, 'estado' => 'SOLICITADA']);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'fecha_programada' => $original]);
    }

    public function test_drive_confirmation_controls_completion(): void
    {
        $this->start();
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasNoErrors();
        Storage::disk('local')->put('contracts/signed.pdf', '%PDF-1.4 signed');
        DB::table('contratos')->where('contrato_id', $this->contract)->update([
            'pdf_contrato_ruta' => 'contracts/signed.pdf', 'firma_revision' => 'VERIFICADA', 'estado' => 'ACTIVO',
        ]);
        app(\App\Services\InstallationDriveService::class)->syncVerifiedContract($this->id);
        $this->assertDatabaseCount('evidencias_drive_instalacion', 2);
        $this->assertFalse(app(\App\Services\InstallationCompletionService::class)->completeIfReady($this->id));
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'EN_PROCESO']);

        config()->set('services.installation_drive', [
            'client_id' => 'demo-client', 'client_secret' => 'demo-secret',
            'refresh_token' => 'demo-refresh', 'folder_id' => 'demo-folder',
            'credentials_path' => null,
        ]);
        $folderIds = [];
        $uploadCount = 0;
        Http::fake(function ($request) use (&$folderIds, &$uploadCount) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) return Http::response(['access_token' => 'demo-access']);
            if (str_contains($url, '/upload/drive/v3/files')) return Http::response(['id' => ++$uploadCount === 1 ? 'drive-contract' : 'drive-photo']);
            if ($request->method() === 'GET' && str_contains($url, '/drive/v3/files?')) {
                $query = $request->data()['q'] ?? '';
                foreach ($folderIds as $name => $id) {
                    if (str_contains($query, "name = '".$name."'")) return Http::response(['files' => [['id' => $id]]]);
                }
                return Http::response(['files' => []]);
            }
            if ($request->method() === 'POST' && str_contains($url, '/drive/v3/files?')) {
                $name = $request->data()['name'];
                $folderIds[$name] = 'folder-'.count($folderIds);
                return Http::response(['id' => $folderIds[$name]]);
            }
            if (str_contains($url, '/drive/v3/files/drive-photo')) return Http::response(['parents' => [$folderIds['Fotos de la instalación']], 'trashed' => false]);
            if (str_contains($url, '/drive/v3/files/drive-contract')) return Http::response(['parents' => [$folderIds['Contrato firmado']], 'trashed' => false]);
            return Http::response([], 404);
        });
        $this->post($this->url('sync-evidence'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('evidencias_drive_instalacion', [
            'pedido_id' => $this->id, 'tipo' => 'FOTO', 'estado' => 'CONFIRMADO',
        ]);
        $this->assertArrayHasKey('Pedido INS-I-0', $folderIds);
        $this->assertArrayHasKey('Fotos de la instalación', $folderIds);
        $this->assertArrayHasKey('Contrato firmado', $folderIds);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'COMPLETADO']);
        $this->assertDatabaseHas('pedido_historial_eventos', ['pedido_id' => $this->id, 'tipo' => 'CIERRE_AUTOMATICO']);
        $this->assertDatabaseHas('pedido_historial_eventos', ['pedido_id' => $this->id, 'tipo' => 'CLIENTE_ACTIVADO_AUTO']);
    }

    public function test_existing_drive_evidence_is_organized_without_uploading_it_again(): void
    {
        $this->start();
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasNoErrors();
        Storage::disk('local')->put('contracts/signed.pdf', '%PDF-1.4 signed');
        DB::table('contratos')->where('contrato_id', $this->contract)->update([
            'pdf_contrato_ruta' => 'contracts/signed.pdf', 'firma_revision' => 'VERIFICADA', 'estado' => 'ACTIVO',
        ]);
        app(\App\Services\InstallationDriveService::class)->syncVerifiedContract($this->id);
        DB::table('evidencias_drive_instalacion')->where('pedido_id', $this->id)->where('tipo', 'FOTO')
            ->update(['estado' => 'CONFIRMADO', 'drive_file_id' => 'old-photo']);
        DB::table('evidencias_drive_instalacion')->where('pedido_id', $this->id)->where('tipo', 'CONTRATO_VALIDADO')
            ->update(['estado' => 'CONFIRMADO', 'drive_file_id' => 'old-contract']);
        config()->set('services.installation_drive', [
            'client_id' => 'demo-client', 'client_secret' => 'demo-secret',
            'refresh_token' => 'demo-refresh', 'folder_id' => 'demo-folder', 'credentials_path' => null,
        ]);

        $folderIds = [];
        $fileParents = ['old-photo' => 'demo-folder', 'old-contract' => 'demo-folder'];
        Http::fake(function ($request) use (&$folderIds, &$fileParents) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) return Http::response(['access_token' => 'demo-access']);
            if ($request->method() === 'GET' && str_contains($url, '/drive/v3/files?')) {
                $query = $request->data()['q'] ?? '';
                foreach ($folderIds as $name => $id) {
                    if (str_contains($query, "name = '".$name."'")) return Http::response(['files' => [['id' => $id]]]);
                }
                return Http::response(['files' => []]);
            }
            if ($request->method() === 'POST' && str_contains($url, '/drive/v3/files?')) {
                $name = $request->data()['name'];
                $folderIds[$name] = 'folder-'.count($folderIds);
                return Http::response(['id' => $folderIds[$name]]);
            }
            foreach ($fileParents as $fileId => $parent) {
                if (str_contains($url, '/drive/v3/files/'.$fileId)) {
                    if ($request->method() === 'PATCH') {
                        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                        if (($query['removeParents'] ?? null) !== 'demo-folder') return Http::response([], 400);
                        $fileParents[$fileId] = $query['addParents'] ?? '';
                    }
                    return Http::response(['id' => $fileId, 'parents' => [$fileParents[$fileId]], 'trashed' => false]);
                }
            }
            return Http::response([], 404);
        });

        $result = app(\App\Services\InstallationDriveService::class)->organizeConfirmed($this->id);
        $this->assertSame(['moved' => 2, 'already_organized' => 0, 'failed' => 0], $result);
        $this->assertSame($folderIds['Fotos de la instalación'], $fileParents['old-photo']);
        $this->assertSame($folderIds['Contrato firmado'], $fileParents['old-contract']);
        $this->assertSame(['moved' => 0, 'already_organized' => 2, 'failed' => 0],
            app(\App\Services\InstallationDriveService::class)->organizeConfirmed($this->id));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/upload/drive/v3/files'));
    }

    public function test_cancelling_a_pending_order_releases_reserved_resources(): void
    {
        DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update([
            'asignado_por' => $this->manager->getKey(), 'fase_flujo' => 'CONFIRMADO',
            'coordinado_en' => now('America/Lima'), 'franja_horaria' => '08:00 a 12:00',
        ]);
        $chief = User::factory()->create(['rol_id' => Role::where('nombre', Role::INSTALLATIONS)->value('rol_id')]);
        $this->requestAndDeliverResources($chief);
        $this->patch('/orders/'.$this->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'CANCELADO']);
        $this->assertDatabaseHas('equipos', ['equipo_id' => $this->equipment, 'estado' => 'DISPONIBLE']);
        $this->assertDatabaseHas('pedido_equipos', ['pedido_id' => $this->id, 'estado_asignacion' => 'CANCELADO']);
        $this->assertDatabaseHas('pedido_materiales', ['pedido_id' => $this->id, 'cantidad_devuelta' => '2.000']);
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
        foreach (['start', 'equipment', 'material', 'observation', 'evidence', 'contract-evidence'] as $action) {
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
        foreach (['start', 'equipment', 'material', 'observation', 'evidence'] as $action) {
            $this->post($this->url($action))->assertForbidden();
        }
        $this->post($this->url('contract-evidence'))->assertNotFound();
        auth()->logout();
        $this->post($this->url('start'))->assertRedirect('/login');
    }

    public function test_start_and_automatic_finish_record_peru_times_and_are_idempotent(): void
    {
        $this->start();
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'estado' => 'EN_PROCESO', 'inicio_en' => '2026-09-26 09:15:00', 'fin_en' => null]);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'EN_PROCESO']);
        $this->travel(2)->minutes();
        $this->start();
        $this->assertDatabaseCount('ejecuciones_instalacion', 1);
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'inicio_en' => '2026-09-26 09:15:00']);
        $this->confirmDriveEvidence();
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'estado' => 'COMPLETADA', 'fin_en' => '2026-09-26 09:17:00']);
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'COMPLETADO']);
        $this->assertDatabaseHas('clientes', [
            'cliente_id' => DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->value('cliente_id'),
            'estado' => 'ACTIVO',
        ]);
        $this->travel(2)->minutes();
        $this->assertFalse(app(\App\Services\InstallationCompletionService::class)->completeIfReady($this->id));
        $this->assertDatabaseHas('ejecuciones_instalacion', ['pedido_id' => $this->id, 'fin_en' => '2026-09-26 09:17:00']);
        $this->get($this->url())->assertOk()->assertDontSee('Finalizar instalación')->assertSee('Completado')->assertDontSee('Iniciar instalación');
    }

    public function test_invalid_state_transitions_are_blocked(): void
    {
        $this->post($this->url('finish'))->assertNotFound();
        foreach (['COMPLETADO', 'CANCELADO'] as $state) {
            DB::table('pedidos_instalacion')->where('pedido_id', $this->id)->update(['estado' => $state]);
            $this->post($this->url('start'))->assertSessionHasErrors('instalacion');
        }
        $this->assertDatabaseCount('ejecuciones_instalacion', 0);
        $this->signIn($this->manager)->post($this->url('start', $this->other))->assertForbidden();
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

    public function test_field_photographs_are_private_and_scoped_to_the_installation(): void
    {
        $this->start();
        $this->post($this->url('evidence'), ['evidence' => $this->photo(), 'descripcion' => 'Foto de instalación'])->assertSessionHasNoErrors();
        $this->post($this->url('evidence'), ['evidence' => $this->photo(), 'descripcion' => 'Segunda foto'])->assertSessionHasNoErrors();
        $photo = DB::table('evidencias_instalacion')->where('tipo', 'FOTOGRAFIA_INSTALACION')->first();
        $secondPhoto = DB::table('evidencias_instalacion')->where('tipo', 'FOTOGRAFIA_INSTALACION')
            ->where('evidencia_id', '!=', $photo->evidencia_id)->first();
        $this->assertSame($this->id, (int) $photo->pedido_id);
        $this->assertNotNull($photo->ejecucion_id);
        $this->assertSame($this->id, (int) $secondPhoto->pedido_id);
        foreach ([$photo, $secondPhoto] as $file) {
            Storage::disk('local')->assertExists($file->ruta_archivo);
        }
        $this->get($this->url('evidence/'.$photo->evidencia_id))->assertOk()->assertDownload();
        $this->get($this->url('evidence/'.$secondPhoto->evidencia_id))->assertOk()->assertDownload();
        $this->assertDatabaseCount('evidencias_contrato', 0);
        $this->signIn($this->manager);
        $this->get($this->url('evidence/'.$secondPhoto->evidencia_id, $this->other))->assertNotFound();
        $this->get($this->url('evidence/'.$photo->evidencia_id, $this->other))->assertNotFound();
    }

    public function test_invalid_evidence_does_not_leave_rows_or_files(): void
    {
        $this->start();
        $this->post($this->url('evidence'), ['evidence' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertSessionHasErrors('evidence');
        $this->post($this->url('evidence'), ['evidence' => $this->photo()->size(5121)])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('evidencias_instalacion', 0);
        $this->assertDatabaseCount('evidencias_contrato', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_signed_pdf_is_managed_from_contracts_not_from_the_technician_panel(): void
    {
        $this->start();
        $this->post($this->url('contract-evidence'), ['evidence' => $this->photo()])->assertNotFound();
        Storage::disk('local')->put('contracts/signed.pdf', '%PDF-1.4 signed');
        DB::table('contratos')->where('contrato_id', $this->contract)->update([
            'pdf_contrato_ruta' => 'contracts/signed.pdf', 'firma_revision' => 'VERIFICADA',
        ]);
        $url = '/contracts/'.$this->contract.'/pdf';
        $this->get($url)->assertForbidden();
        $this->signIn($this->manager)->get('/contracts/'.$this->contract)->assertOk()->assertSee('PDF adjunto disponible');
        $this->get($url)->assertOk()->assertDownload();
        $office = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $this->signIn($office)->get($url)->assertOk()->assertDownload();
        $this->assertDatabaseCount('evidencias_contrato', 0);
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
        $this->post($this->url('evidence'), ['evidence' => $this->photo()])->assertSessionHasNoErrors();
        $photos = DB::table('evidencias_instalacion')->where('tipo', 'FOTOGRAFIA_INSTALACION')->get();
        $photo = $photos->first();
        $secondPhoto = $photos->last();
        $photoUrl = $this->url('evidence/'.$photo->evidencia_id.'/preview');
        $secondPhotoUrl = $this->url('evidence/'.$secondPhoto->evidencia_id.'/preview');
        foreach ([$photoUrl, $secondPhotoUrl] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->get($this->url())->assertOk()->assertSee($photoUrl)->assertSee('Cierre automático')->assertDontSee('data-confirm-finish', false)->assertSee('data-evidence-input', false);
        $this->signIn($this->manager)->get($this->url('evidence/'.$photo->evidencia_id.'/preview', $this->other))->assertNotFound();
        $this->get($this->url('evidence/'.$secondPhoto->evidencia_id.'/preview', $this->other))->assertNotFound();
        Storage::disk('local')->put($photo->ruta_archivo, '<html>no es una imagen</html>');
        $this->get($photoUrl)->assertNotFound();
        $otherTech = User::factory()->create(['rol_id' => Role::where('nombre', Role::TECHNICIAN)->value('rol_id')]);
        $this->signIn($otherTech)->get($secondPhotoUrl)->assertNotFound();
        $this->get($photoUrl)->assertNotFound();
    }

    public function test_observation_is_escaped_and_completed_installations_are_read_only(): void
    {
        $this->start();
        $this->post($this->url('observation'), ['observacion_tecnica' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
        $this->get($this->url())->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->confirmDriveEvidence();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id' => $this->id, 'estado' => 'COMPLETADO']);
        foreach ([
            'material' => ['material_id' => $this->material, 'cantidad_utilizada' => '1'],
            'equipment' => ['equipo_id' => $this->equipment],
            'observation' => ['observacion_tecnica' => 'Cambio'],
            'evidence' => ['evidence' => $this->photo()],
        ] as $action => $data) {
            $this->post($this->url($action), $data)->assertSessionHasErrors('instalacion');
        }
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }
}
