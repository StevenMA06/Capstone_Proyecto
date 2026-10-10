<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_grouped_permissions_render_with_their_original_codes(): void
    {
        $admin = Role::where('nombre', Role::ADMIN)->firstOrFail();
        $office = Role::where('nombre', Role::OFFICE)->firstOrFail();
        $this->signIn(User::factory()->create(['rol_id' => $admin->getKey()]));

        foreach (['/roles', '/roles?role='.$office->getKey(), '/roles?role='.$admin->getKey()] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('Permisos por módulo');
            foreach (AccessCatalog::permissions() as $code => $permission) {
                $response->assertSee('value="'.$code.'"', false);
            }
            $response->assertDontSee('name="permissions[]" value="0"', false);
        }
    }
}
