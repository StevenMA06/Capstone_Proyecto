<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccessService;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            Role::OFFICE=>[['office.view','clients.create','clients.update','sales.create','orders.create','equipments.availability'],['dashboard.view','technician.panel','clients.status','contracts.update','payments.view','reports.view','kardex.view']],
            Role::TECHNICIAN=>[['technician.panel','technician.material','contracts.assigned','equipments.assigned','kardex.related','plans.view','zones.view'],['dashboard.view','office.view','contracts.view','equipments.view','kardex.view','technician.supervise']],
            Role::WAREHOUSE=>[['equipments.create','equipments.import','materials.adjustment','kardex.view'],['clients.view','orders.view','reports.view','bi.view']],
            Role::IT=>[[],array_keys(AccessCatalog::permissions())],
            Role::INSTALLATIONS=>[['technician.supervise','orders.schedule','orders.cancel','payments.view'],['employees.view','access.view','clients.update','reports.view']],
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
        $admin=$this->actor(Role::ADMIN);$office=$this->actor(Role::OFFICE);$chief=$this->actor(Role::INSTALLATIONS);
        $client=Client::create(['tipo_documento'=>'DNI','numero_documento'=>'12345678','nombres_razon_social'=>'Cliente prueba','direccion'=>'Calle prueba','creado_por'=>$admin->getKey()]);
        ClientHistoryFixtures::attach($client,$admin,'ORD');
        $order=DB::table('pedidos_instalacion')->where('codigo','ORD-I-0')->first();
        $this->signIn($office)->post('/orders',['contrato_id'=>$order->contrato_id])->assertSessionHasNoErrors();
        $new=DB::table('pedidos_instalacion')->orderByDesc('pedido_id')->first();
        $this->post('/orders',['contrato_id'=>$order->contrato_id])->assertSessionHasErrors('contrato_id');
        $this->patch('/orders/'.$new->pedido_id.'/schedule',[])->assertForbidden();
        Employee::whereKey($order->tecnico_id)->update(['cargo'=>'Técnico de Campo']);
        $this->signIn($chief)->patch('/orders/'.$new->pedido_id.'/schedule',['tecnico_id'=>$order->tecnico_id,'fecha_programada'=>now()->addDay()->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pedidos_instalacion',['pedido_id'=>$new->pedido_id,'tecnico_id'=>$order->tecnico_id,'estado'=>'PROGRAMADO']);
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
