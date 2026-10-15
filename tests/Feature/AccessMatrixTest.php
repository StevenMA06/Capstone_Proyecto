<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccessService;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $name): User
    {
        return User::factory()->create(['rol_id'=>Role::where('nombre',$name)->value('rol_id')]);
    }

    public function test_defaults_respect_panels_reserved_modules_and_optional_permissions(): void
    {
        $expectations=[
            Role::MANAGER=>[['dashboard.view','roles.manage','technician.supervise'],['office.view','technician.panel','technician.start','employees.view','access.view']],
            Role::OFFICE=>[['office.view','office.coordination','clients.create','clients.update','sales.create','equipments.availability'],['dashboard.view','technician.panel','clients.status','contracts.update','orders.view','payments.view','reports.view','kardex.view']],
            Role::TECHNICIAN=>[['technician.panel','technician.material','contracts.assigned','equipments.assigned','kardex.related','plans.view','zones.view'],['dashboard.view','office.view','contracts.view','equipments.view','kardex.view','technician.supervise']],
            Role::WAREHOUSE=>[['equipments.create','equipments.import','materials.adjustment','kardex.view'],['clients.view','orders.view','reports.view','bi.view']],
            Role::IT=>[[],array_keys(AccessCatalog::permissions())],
            Role::INSTALLATIONS=>[['technician.supervise','orders.schedule','orders.assigned','orders.resources.request','orders.cancel','payments.view'],['employees.view','access.view','clients.update','reports.view']],
        ];
        foreach ($expectations as $name=>[$allowed,$denied]) {
            $user=$this->actor($name);
            foreach ($allowed as $code) $this->assertTrue($user->hasPermission($code),$name.' '.$code);
            foreach ($denied as $code) $this->assertFalse($user->hasPermission($code),$name.' '.$code);
        }
        $this->signIn($this->actor(Role::MANAGER))->get('/dashboard/call-center')->assertForbidden();
        $this->get('/technician')->assertForbidden();
        $this->get('/executions')->assertOk();
        $this->get('/employees')->assertForbidden();
        $this->get('/access-history')->assertForbidden();
        $this->signIn($this->actor(Role::OFFICE))->get('/dashboard')->assertRedirect('/dashboard/call-center');
        $this->get('/dashboard/call-center')->assertOk();
        $this->get('/dashboard/gerencia')->assertForbidden();
        $this->get('/availability/equipment')->assertOk();
        $this->get('/equipments')->assertForbidden();
        $this->signIn($this->actor(Role::ADMIN))->get('/employees')->assertOk();
        $this->get('/access-history')->assertOk();
    }

    public function test_contract_cancellation_inbox_has_a_separate_view_permission(): void
    {
        $manager = $this->actor(Role::MANAGER);
        $office = $this->actor(Role::OFFICE);

        $this->assertSame('contracts.cancel.inbox', AccessCatalog::forRoute('contracts.cancellations'));
        $this->assertContains('contracts.cancel.inbox', AccessCatalog::dependencies('contracts.cancel.approve'));
        $this->signIn($manager)->get('/contracts/cancellation-requests')->assertOk()->assertSee('Bandeja de solicitudes');
        $this->get('/contracts')->assertSee(route('contracts.cancellations'), false);
        $this->signIn($office)->get('/contracts/cancellation-requests')->assertForbidden();
    }

    public function test_reserved_permissions_cannot_be_granted_even_through_a_pivot_record(): void
    {
        $manager=$this->actor(Role::MANAGER);$role=Role::where('nombre',Role::IT)->firstOrFail();
        $this->signIn($manager)->put('/roles/'.$role->getKey(),['nombre'=>$role->nombre,'descripcion'=>'TI','estado'=>'ACTIVO','permissions'=>['employees.view','access.view']])->assertSessionHasErrors('permissions');
        foreach (['employees.view','access.view'] as $code) DB::table('rol_permiso')->insert(['rol_id'=>$role->getKey(),'permiso_id'=>DB::table('permisos')->where('codigo',$code)->value('permiso_id'),'permitido'=>true]);
        $user=$this->actor(Role::IT);
        $this->signIn($user)->get('/employees')->assertForbidden();
        $this->get('/access-history')->assertForbidden();
    }

    public function test_optional_permission_is_effective_immediately_and_actions_are_separate(): void
    {
        $manager=$this->actor(Role::MANAGER);$user=$this->actor(Role::IT);$role=$user->role;
        $service=app(RoleAccessService::class);
        $service->saveRole($manager,$role,['nombre'=>$role->nombre,'descripcion'=>'TI','estado'=>'ACTIVO','permissions'=>['materials.view','materials.create']]);
        $this->signIn($user)->get('/materials/create')->assertOk()->assertSee('name="nombre"',false)->assertSee('name="stock_minimo"',false);
        $this->get('/materials/create')->assertDontSee('name="estado" disabled',false);
        $this->get('/materials')->assertOk()->assertDontSee('Importar equipos');
        $this->get('/kardex')->assertForbidden();
        $service->saveRole($manager,$role,['nombre'=>$role->nombre,'descripcion'=>'TI','estado'=>'ACTIVO','permissions'=>['materials.view']]);
        $this->get('/materials/create')->assertForbidden();
        $this->post('/materials',[])->assertForbidden();
        $this->get('/materials')->assertOk()->assertDontSee('href="'.route('materials.create').'"',false);
    }

    public function test_assigned_contracts_equipment_and_kardex_do_not_disclose_other_assignments(): void
    {
        $admin=$this->actor(Role::ADMIN);
        $client=Client::create(['tipo_documento'=>'DNI','numero_documento'=>'12345678','nombres_razon_social'=>'Cliente prueba','direccion'=>'Calle prueba','creado_por'=>$admin->getKey()]);
        ClientHistoryFixtures::attach($client,$admin,'MAT');
        $order=DB::table('pedidos_instalacion')->where('codigo','MAT-I-0')->first();
        $other=DB::table('pedidos_instalacion')->where('codigo','MAT-I-3')->first();
        $tech=$this->actor(Role::TECHNICIAN);$tech->update(['empleado_id'=>$order->tecnico_id]);
        foreach ([[$order,'PROPIO'],[$other,'AJENO']] as [$item,$code]) {
            $equipment=DB::table('equipos')->insertGetId(['codigo'=>$code,'nombre'=>$code,'tipo'=>'Router','estado'=>'ASIGNADO','creado_por'=>$admin->getKey()]);
            DB::table('pedido_equipos')->insert(['pedido_id'=>$item->pedido_id,'equipo_id'=>$equipment,'asignado_por'=>$admin->getKey(),'estado_asignacion'=>'ASIGNADO']);
            DB::table('movimientos_inventario')->insert(['pedido_id'=>$item->pedido_id,'equipo_id'=>$equipment,'tipo_movimiento'=>'ASIGNACION','cantidad'=>1,'responsable_id'=>$admin->getKey(),'fecha_movimiento'=>now()]);
        }
        $this->signIn($tech)->get('/assigned/contracts')->assertOk()->assertSee('MAT-C-0')->assertDontSee('MAT-C-3');
        $this->get('/assigned/equipment')->assertOk()->assertSee('PROPIO')->assertDontSee('AJENO');
        $this->get('/assigned/kardex')->assertOk()->assertSee('PROPIO')->assertDontSee('AJENO');
        $this->get('/contracts/'.$other->contrato_id)->assertForbidden();
        $this->get('/kardex')->assertForbidden();
        $this->get('/assigned/orders')->assertOk()->assertSee('MAT-I-0')->assertDontSee('MAT-I-3');
    }

    public function test_supervision_is_read_only_and_client_history_needs_its_own_module_permission(): void
    {
        $manager=$this->actor(Role::MANAGER);
        $client=Client::create(['tipo_documento'=>'DNI','numero_documento'=>'12345678','nombres_razon_social'=>'Cliente prueba','direccion'=>'Calle prueba','creado_por'=>$manager->getKey()]);
        ClientHistoryFixtures::attach($client,$manager,'SUP');
        $order=DB::table('pedidos_instalacion')->where('codigo','SUP-I-2')->first();
        $this->signIn($manager)->get('/technician/installations/'.$order->pedido_id)->assertOk()->assertDontSee('Iniciar instalación');
        $this->post('/technician/installations/'.$order->pedido_id.'/start')->assertForbidden();
        $office=$this->actor(Role::OFFICE);
        $this->signIn($office)->get('/clients/'.$client->getKey().'?tab=pagos')->assertForbidden();
        $this->get('/clients/'.$client->getKey())->assertOk()->assertDontSee('Confirmar inhabilitación');
        $this->patch('/clients/'.$client->getKey().'/deactivate')->assertForbidden();
    }

    public function test_orders_separate_registration_from_assignment_and_block_started_changes(): void
    {
        Storage::fake('local');
        $admin=$this->actor(Role::ADMIN);$office=$this->actor(Role::OFFICE);$chief=$this->actor(Role::INSTALLATIONS);
        $client=Client::create(['tipo_documento'=>'DNI','numero_documento'=>'12345678','nombres_razon_social'=>'Cliente prueba','direccion'=>'Calle prueba','creado_por'=>$admin->getKey()]);
        ClientHistoryFixtures::attach($client,$admin,'ORD');
        $order=DB::table('pedidos_instalacion')->where('codigo','ORD-I-0')->first();
        $base=DB::table('contratos')->where('contrato_id',$order->contrato_id)->first();
        $contractId=DB::table('contratos')->insertGetId([
            'codigo'=>'ORD-C-NUEVO','cliente_id'=>$base->cliente_id,'plan_id'=>$base->plan_id,
            'zona_id'=>$base->zona_id,'responsable_id'=>$base->responsable_id,
            'fecha_inicio'=>$base->fecha_inicio,'fecha_fin'=>$base->fecha_fin,
            'duracion_meses'=>$base->duracion_meses,'precio_contratado'=>$base->precio_contratado,
            'direccion_servicio'=>$base->direccion_servicio,'estado'=>'ACTIVO',
        ]);
        $this->signIn($office)->get('/dashboard/call-center/visitas')->assertOk();
        $this->get('/orders')->assertForbidden();
        $this->post('/orders',['contrato_id'=>$contractId])->assertStatus(405);
        app(\App\Services\InstallationOrderService::class)->createForContract(DB::table('contratos')->where('contrato_id',$contractId)->first(),$chief->getKey());
        $this->signIn($chief)->post('/orders',['contrato_id'=>$contractId])->assertStatus(405);
        $new=DB::table('pedidos_instalacion')->orderByDesc('pedido_id')->first();
        $this->signIn($office)->patch('/orders/'.$new->pedido_id.'/schedule',[])->assertForbidden();
        $this->patch('/orders/'.$new->pedido_id.'/coordinate', [])->assertNotFound();
        DB::table('contratos')->where('contrato_id', $contractId)->update(['fecha_inicio' => now('America/Lima')->subDay()->toDateString()]);
        $this->get('/dashboard/call-center/visitas?search=ORD-C-NUEVO')->assertOk()
            ->assertSee('Confirmar fecha de visita')->assertSee('ORD-C-NUEVO')
            ->assertSee('name="fecha_visita"', false)
            ->assertSee('value="" required data-visit-date', false)
            ->assertSee('ya no tiene un horario disponible')
            ->assertSee('Mañana · 08:00 a 12:00');
        $futureContractDate = now('America/Lima')->addDays(2)->toDateString();
        DB::table('contratos')->where('contrato_id', $contractId)->update(['fecha_inicio' => $futureContractDate]);
        $this->get('/dashboard/call-center/visitas?search=ORD-C-NUEVO')->assertOk()
            ->assertSee('value="'.$futureContractDate.'" required data-visit-date', false);
        $this->signIn($chief)->get('/orders?search=ORD-C-NUEVO')->assertOk()
            ->assertSee('order-next-attention')->assertSee('Call Center debe confirmar la fecha con el cliente');
        $this->signIn($office);
        $this->patch('/dashboard/call-center/visitas/'.$new->pedido_id,[
            'fecha_visita'=>now('America/Lima')->addDay()->toDateString(),
            'hora_inicio'=>'13:00', 'franja_horaria'=>'08:00 a 12:00',
            'canal_coordinacion'=>'LLAMADA',
        ])->assertSessionHasErrors('hora_inicio');
        $this->patch('/dashboard/call-center/visitas/'.$new->pedido_id,[
            'fecha_visita'=>now('America/Lima')->addDay()->toDateString(), 'hora_inicio'=>'09:00',
            'franja_horaria'=>'08:00 a 12:00','canal_coordinacion'=>'LLAMADA',
        ])->assertSessionHasNoErrors();
        $this->signIn($chief)->get('/orders?search=ORD-C-NUEVO')->assertOk()
            ->assertSee('08:00 a 12:00');
        Employee::whereKey($order->tecnico_id)->update(['cargo'=>'Técnico de Campo']);
        \Illuminate\Support\Facades\Mail::fake();
        $this->signIn($chief)->patch('/orders/'.$new->pedido_id.'/schedule',['tecnico_id'=>$order->tecnico_id])
            ->assertSessionHasErrors('pedido');
        $equipmentId=DB::table('equipos')->insertGetId(['codigo'=>'EQ-ORD-NEW','nombre'=>'Router nuevo','tipo'=>'Router','creado_por'=>$admin->getKey()]);
        $materialId=DB::table('materiales')->insertGetId(['codigo'=>'MAT-ORD-NEW','nombre'=>'Cable nuevo','unidad_base'=>'m','stock_actual'=>'10.000','creado_por'=>$admin->getKey()]);
        $this->post('/orders/'.$new->pedido_id.'/equipment',['equipo_tipo'=>'Router'])->assertSessionHasNoErrors();
        $this->post('/orders/'.$new->pedido_id.'/material',['material_id'=>$materialId,'cantidad_asignada'=>'2'])->assertSessionHasNoErrors();
        $warehouse=$this->actor(Role::WAREHOUSE);
        foreach (DB::table('pedido_solicitudes_recurso')->where('pedido_id',$new->pedido_id)->get() as $resourceRequest) {
            $this->signIn($warehouse)->patch('/warehouse/requests/'.$resourceRequest->solicitud_id.'/fulfill',
                $resourceRequest->tipo === 'EQUIPO' ? ['equipo_id'=>$equipmentId] : [])->assertSessionHasNoErrors();
        }
        $this->signIn($chief)->patch('/orders/'.$new->pedido_id.'/schedule',['tecnico_id'=>$order->tecnico_id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('orders.index', ['search' => $new->codigo]).'#pedido-'.$new->pedido_id);
        $this->get('/orders?search=ORD-C-NUEVO')->assertOk()
            ->assertSee('Pedidos: asignación y recursos')
            ->assertSee('Supervisión de instalaciones')
            ->assertSee('Solicitar recursos adicionales')
            ->assertSee('Enviar solicitud a Almacén')
            ->assertSee('Solicitudes del pedido');
        $this->assertDatabaseHas('pedidos_instalacion',['pedido_id'=>$new->pedido_id,'tecnico_id'=>$order->tecnico_id,'estado'=>'PROGRAMADO']);
        $this->signIn($office)->patch('/dashboard/call-center/visitas/'.$new->pedido_id, [
            'fecha_visita'=>now('America/Lima')->addDays(2)->toDateString(), 'hora_inicio'=>'14:00',
            'franja_horaria'=>'14:00 a 18:00','canal_coordinacion'=>'WHATSAPP',
            'motivo_codigo'=>'OTRO',
        ])->assertSessionHasErrors('motivo_reprogramacion');
        $this->patch('/dashboard/call-center/visitas/'.$new->pedido_id, [
            'fecha_visita'=>now('America/Lima')->addDays(2)->toDateString(), 'hora_inicio'=>'14:00',
            'franja_horaria'=>'14:00 a 18:00','canal_coordinacion'=>'WHATSAPP',
            'motivo_codigo'=>'SOLICITUD_CLIENTE',
            'motivo_reprogramacion'=>'Cliente pidió otra fecha',
            'evidencia_acuerdo'=>UploadedFile::fake()->createWithContent('acuerdo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN9sAAAAASUVORK5CYII=')),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', ['pedido_id'=>$new->pedido_id,'estado'=>'PROGRAMADO']);
        $requestId=DB::table('pedido_reprogramaciones')->where('pedido_id',$new->pedido_id)->value('reprogramacion_id');
        $manager=$this->actor(Role::MANAGER);
        $this->signIn($manager)->patch('/orders/'.$new->pedido_id.'/reprogramming/'.$requestId.'/approve', ['evidencia_revisada'=>'1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion', [
            'pedido_id'=>$new->pedido_id,'estado'=>'REPROGRAMADO',
            'fase_flujo'=>'ESPERANDO_CONFIRMACION_TECNICO',
        ]);
        $this->signIn($chief);
        DB::table('pedidos_instalacion')->where('pedido_id',$new->pedido_id)->update(['estado'=>'EN_PROCESO']);
        $this->patch('/orders/'.$new->pedido_id.'/cancel')->assertSessionHasErrors('pedido');
    }

    public function test_migration_preserves_role_ids_and_existing_assignments(): void
    {
        $manager=$this->actor(Role::MANAGER);$office=$this->actor(Role::OFFICE);
        $manager->roles()->syncWithoutDetaching([$office->rol_id]);
        DB::table('roles')->where('rol_id',$manager->rol_id)->update(['nombre'=>'Gerente']);
        DB::table('roles')->where('rol_id',$office->rol_id)->update(['nombre'=>'Usuario de oficina']);
        (require database_path('migrations/2026_10_05_000001_apply_access_matrix.php'))->up();
        $this->assertDatabaseHas('roles',['rol_id'=>$manager->rol_id,'nombre'=>Role::MANAGER]);
        $this->assertDatabaseHas('roles',['rol_id'=>$office->rol_id,'nombre'=>Role::OFFICE]);
        $this->assertDatabaseHas('usuario_rol',['usuario_id'=>$manager->getKey(),'rol_id'=>$office->rol_id]);
        $this->assertTrue($manager->hasPermission('office.view')); // union of both active roles
        $this->assertFalse($manager->hasPermission('employees.view'));
    }

    public function test_legacy_custom_execution_permission_does_not_expand_assignment_scope(): void
    {
        $role=Role::create(['nombre'=>'Instalador externo','estado'=>'ACTIVO']);
        DB::table('permisos')->updateOrInsert(['codigo'=>'technician.manage'],['modulo'=>'Instalaciones','accion'=>'Administrar','descripcion'=>'Permiso anterior','estado'=>'ACTIVO']);
        DB::table('rol_permiso')->insert(['rol_id'=>$role->getKey(),'permiso_id'=>DB::table('permisos')->where('codigo','technician.manage')->value('permiso_id'),'permitido'=>true]);
        $user=User::factory()->create(['rol_id'=>$role->getKey()]);
        (require database_path('migrations/2026_10_05_000001_apply_access_matrix.php'))->up();
        $this->assertTrue($user->hasPermission('technician.start'));
        $this->assertFalse($user->hasPermission('technician.supervise'));
    }

    public function test_equipment_edit_permission_cannot_change_state(): void
    {
        $manager=$this->actor(Role::MANAGER);
        $role=app(RoleAccessService::class)->saveRole($manager,null,['nombre'=>'Editor de equipos','descripcion'=>'Solo datos','estado'=>'ACTIVO','permissions'=>['equipments.view','equipments.update']]);
        $user=User::factory()->create(['rol_id'=>$role->getKey()]);
        $id=DB::table('equipos')->insertGetId(['codigo'=>'EDIT','nombre'=>'Router','tipo'=>'Router','condicion'=>'NUEVO','estado'=>'DISPONIBLE','creado_por'=>$manager->getKey()]);
        $data=['codigo'=>'EDIT','nombre'=>'Router editado','tipo'=>'Router','condicion'=>'NUEVO','estado'=>'DISPONIBLE'];
        $this->signIn($user)->put('/equipments/'.$id,$data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('equipos',['equipo_id'=>$id,'nombre'=>'Router editado']);
        $this->put('/equipments/'.$id,array_merge($data,['estado'=>'INACTIVO']))->assertForbidden();
        $this->assertDatabaseHas('equipos',['equipo_id'=>$id,'estado'=>'DISPONIBLE']);
    }
}
