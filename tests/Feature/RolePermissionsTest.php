<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::factory()->create(['rol_id' => Role::where('nombre', $role)->firstOrFail()->getKey()]);
    }

    private function picture(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN9sAAAAASUVORK5CYII='));
    }

    public function test_admin_and_manager_access_all_modules(): void
    {
        foreach ([Role::ADMIN, Role::MANAGER] as $role) {
            $this->signIn($this->account($role));
            foreach (['dashboard', 'clients', 'users', 'access-history', 'plans', 'contracts', 'payments', 'equipment', 'materials', 'installations', 'technician'] as $path) {
                $this->get('/'.$path)->assertOk();
            }
            $this->get('/users')->assertSee('Ver ficha')->assertSee('images/lunnys-peru.png');
        }
    }

    public function test_office_can_only_access_office_modules(): void
    {
        $this->signIn($this->account(Role::OFFICE));
        foreach (['dashboard', 'clients', 'plans', 'contracts', 'payments'] as $path) {
            $this->get('/'.$path)->assertOk();
        }
        foreach (['users', 'access-history', 'equipment', 'materials', 'installations', 'technician'] as $path) {
            $this->get('/'.$path)->assertForbidden();
        }
        $this->get('/dashboard')->assertDontSee('href="'.route('technician.index').'"', false)->assertDontSee('equipment-dialog');
    }

    public function test_owner_cannot_be_deactivated_demoted_or_deleted(): void
    {
        $admin = $this->account(Role::ADMIN);
        $this->signIn($admin)->patch('/users/'.$admin->getKey().'/status', ['is_active' => false])->assertSessionHasErrors('rol_id');
        $this->put('/users/'.$admin->getKey(), ['nombre_usuario' => $admin->nombre_usuario, 'correo' => $admin->correo, 'rol_id' => Role::where('nombre', Role::MANAGER)->value('rol_id')])->assertSessionHasErrors('rol_id');
        $this->assertTrue($admin->fresh()->isAdministrator());
        try {
            $admin->delete();
            $this->fail('The owner must not be deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }
        $this->assertDatabaseHas('usuarios', ['usuario_id' => $admin->getKey(), 'estado' => 'ACTIVO']);
    }

    public function test_manager_cannot_take_over_owner_credentials_or_assign_admin(): void
    {
        $admin = $this->account(Role::ADMIN);
        $manager = $this->account(Role::MANAGER);
        $data = ['nombre_usuario' => 'new.owner', 'correo' => 'new@example.test', 'rol_id' => $admin->rol_id, 'password' => 'ClaveSegura12345', 'password_confirmation' => 'ClaveSegura12345'];
        $this->signIn($manager)->get('/users/'.$admin->getKey().'/edit')->assertForbidden();
        $this->put('/users/'.$admin->getKey(), $data)->assertForbidden();
        $this->patch('/users/'.$admin->getKey().'/status', ['is_active' => false])->assertForbidden();
        $this->post('/users', $data)->assertSessionHasErrors('rol_id');
        $this->get('/users/create')->assertDontSee('value="'.$admin->rol_id.'"', false);
        $this->get('/users/'.$admin->getKey())->assertOk()->assertDontSee('Editar cuenta');
    }

    public function test_technician_assignment_scopes_read_upload_and_download(): void
    {
        Storage::fake('local');
        $manager = $this->account(Role::MANAGER);
        $client = Client::create(['tipo_documento' => 'DNI', 'numero_documento' => '00000001', 'nombres_razon_social' => 'Prueba', 'direccion' => 'Dirección', 'creado_por' => $manager->getKey()]);
        ClientHistoryFixtures::attach($client, $manager, 'TECH');
        $assigned = DB::table('pedidos_instalacion')->where('codigo', 'TECH-I-0')->first();
        $unassigned = DB::table('pedidos_instalacion')->where('codigo', 'TECH-I-3')->first();
        $tech = $this->account(Role::TECHNICIAN);
        $tech->update(['empleado_id' => $assigned->tecnico_id]);
        DB::table('pedidos_instalacion')->where('pedido_id', $assigned->pedido_id)->update(['estado' => 'PROGRAMADO']);
        $this->signIn($tech)->get('/dashboard')->assertRedirect('/technician');
        $this->get('/technician')->assertOk()->assertSee('TECH-I-0')->assertDontSee('TECH-I-3')->assertDontSee('href="'.route('clients.index').'"', false);
        foreach (['clients', 'plans', 'contracts', 'payments', 'users', 'equipment', 'installations', 'access-history'] as $path) {
            $this->get('/'.$path)->assertForbidden();
        }
        $this->get('/technician/installations/'.$unassigned->pedido_id)->assertNotFound();
        $this->post('/technician/installations/'.$unassigned->pedido_id.'/evidence', ['evidence' => $this->picture()])->assertNotFound();
        $this->post('/technician/installations/'.$assigned->pedido_id.'/start')->assertSessionHasNoErrors();
        $this->post('/technician/installations/'.$assigned->pedido_id.'/evidence', ['evidence' => $this->picture(), 'descripcion' => '<script>prueba</script>'])->assertSessionHasNoErrors()->assertRedirect();
        $record = DB::table('evidencias_instalacion')->first();
        Storage::disk('local')->assertExists($record->ruta_archivo);
        $url = '/technician/installations/'.$assigned->pedido_id.'/evidence/'.$record->evidencia_id;
        $this->get($url)->assertOk()->assertDownload();
        $this->get('/technician/installations/'.$assigned->pedido_id)->assertSee('&lt;script&gt;prueba&lt;/script&gt;', false);
        $this->post('/technician/installations/'.$assigned->pedido_id.'/evidence', ['evidence' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertSessionHasErrors('evidence');
        $this->post('/technician/installations/'.$assigned->pedido_id.'/evidence', ['evidence' => $this->picture()->size(5121)])->assertSessionHasErrors('evidence');
        $this->signIn($this->account(Role::TECHNICIAN))->get('/technician')->assertOk()->assertDontSee('TECH-I-0');
        $this->get($url)->assertNotFound();
        $this->get('/technician/installations/'.$assigned->pedido_id)->assertNotFound();
        $this->signIn($this->account(Role::OFFICE))->get($url)->assertForbidden();
        $this->assertDatabaseCount('evidencias_instalacion', 1);
    }

    public function test_migration_preserves_legacy_accounts_and_disabled_access(): void
    {
        $legacy = Role::create(['nombre' => 'Gerencia', 'estado' => 'ACTIVO']);
        $disabled = Role::create(['nombre' => 'Personal autorizado', 'estado' => 'INACTIVO']);
        $old = User::factory()->create(['rol_id' => $legacy->getKey()]);
        $blocked = User::factory()->create(['rol_id' => $disabled->getKey()]);
        $migration = require database_path('migrations/2026_09_14_000002_normalize_user_roles.php');
        $migration->up();
        $migration->up();
        $this->assertSame(Role::MANAGER, $old->fresh()->role->nombre);
        $this->assertSame(Role::OFFICE, $blocked->fresh()->role->nombre);
        $this->assertFalse($blocked->fresh()->canAccess());
        $this->assertSame(4, Role::where('estado', 'ACTIVO')->count());
        $this->assertDatabaseCount('usuarios', 2);
    }

    public function test_custom_roles_can_login_without_implicit_permissions_and_be_assigned(): void
    {
        $role = Role::create(['nombre' => 'Rol desconocido', 'estado' => 'ACTIVO']);
        $unknown = User::factory()->create(['rol_id' => $role->getKey()]);
        $this->assertTrue($unknown->canAccess());
        $this->assertFalse($unknown->hasPermission('clients.view'));
        $this->signIn($this->account(Role::ADMIN))->post('/users', ['nombre_usuario' => 'unknown', 'correo' => 'unknown@example.test', 'rol_id' => $role->getKey(), 'password' => 'ClaveSegura12345', 'password_confirmation' => 'ClaveSegura12345'])->assertSessionHasNoErrors();
    }
}
