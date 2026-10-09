<?php

use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['office.coordination'] as $code) {
                DB::table('permisos')->updateOrInsert(['codigo' => $code], AccessCatalog::permissions()[$code] + ['estado' => 'ACTIVO']);
            }

            $permissionId = DB::table('permisos')->where('codigo', 'office.coordination')->value('permiso_id');
            foreach ([Role::ADMIN, Role::MANAGER, Role::OFFICE] as $name) {
                $roleId = DB::table('roles')->where('nombre', $name)->value('rol_id');
                if ($roleId) {
                    DB::table('rol_permiso')->updateOrInsert(
                        ['rol_id' => $roleId, 'permiso_id' => $permissionId],
                        ['permitido' => true]
                    );
                }
            }

            $officeRoleId = DB::table('roles')->where('nombre', Role::OFFICE)->value('rol_id');
            if ($officeRoleId) {
                DB::table('rol_permiso')->where('rol_id', $officeRoleId)
                    ->whereIn('permiso_id', DB::table('permisos')->whereIn('codigo', ['orders.view', 'orders.create'])->select('permiso_id'))
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Do not restore operational permissions to Call Center on rollback.
    }
};
