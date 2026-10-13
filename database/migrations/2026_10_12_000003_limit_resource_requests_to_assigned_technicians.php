<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roleId = DB::table('roles')->where('nombre', Role::INSTALLATIONS)->value('rol_id');
        $permissionId = DB::table('permisos')->where('codigo', 'orders.resources.request')->value('permiso_id');
        if ($roleId && $permissionId) {
            DB::table('rol_permiso')->where('rol_id', $roleId)->where('permiso_id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('nombre', Role::INSTALLATIONS)->value('rol_id');
        $permissionId = DB::table('permisos')->where('codigo', 'orders.resources.request')->value('permiso_id');
        if ($roleId && $permissionId) {
            DB::table('rol_permiso')->updateOrInsert(
                ['rol_id' => $roleId, 'permiso_id' => $permissionId],
                ['permitido' => true]
            );
        }
    }
};
