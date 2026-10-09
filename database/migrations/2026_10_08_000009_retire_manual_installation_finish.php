<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissionId = DB::table('permisos')->where('codigo', 'technician.finish')->value('permiso_id');
        if ($permissionId) {
            DB::table('rol_permiso')->where('permiso_id', $permissionId)->delete();
            DB::table('permisos')->where('permiso_id', $permissionId)->update(['estado' => 'INACTIVO']);
        }
    }

    public function down(): void {}
};
