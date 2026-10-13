<?php

use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permisos') || ! Schema::hasTable('rol_permiso')) {
            return;
        }

        DB::transaction(function (): void {
            $permissions = AccessCatalog::permissions();
            foreach (['orders.assigned', 'orders.resources.request'] as $code) {
                $data = $permissions[$code];
                DB::table('permisos')->updateOrInsert(['codigo' => $code], $data + ['estado' => 'ACTIVO']);
            }

            $roleId = DB::table('roles')->where('nombre', Role::INSTALLATIONS)->value('rol_id');
            if (! $roleId) return;

            $permissionIds = DB::table('permisos')->whereIn('codigo', ['orders.assigned', 'orders.resources.request'])
                ->pluck('permiso_id');
            foreach ($permissionIds as $permissionId) {
                DB::table('rol_permiso')->updateOrInsert(
                    ['rol_id' => $roleId, 'permiso_id' => $permissionId],
                    ['permitido' => true],
                );
            }
        });
    }

    public function down(): void
    {
        // Keep role assignments intact; they may have been customized after rollout.
    }
};
