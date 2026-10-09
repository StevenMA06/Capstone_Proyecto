<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roleId = DB::table('roles')->where('nombre', Role::MANAGER)->value('rol_id');
        $permissionId = DB::table('permisos')->where('codigo', 'warehouse.view')->where('estado', 'ACTIVO')->value('permiso_id');

        if ($roleId && $permissionId) {
            DB::table('rol_permiso')->updateOrInsert(
                ['rol_id' => $roleId, 'permiso_id' => $permissionId],
                ['permitido' => true],
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('nombre', Role::MANAGER)->value('rol_id');
        $permissionId = DB::table('permisos')->where('codigo', 'warehouse.view')->value('permiso_id');

        if ($roleId && $permissionId) {
            DB::table('rol_permiso')->where('rol_id', $roleId)->where('permiso_id', $permissionId)->delete();
        }
    }
};
