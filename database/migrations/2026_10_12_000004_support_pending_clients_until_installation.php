<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE clientes MODIFY estado ENUM('ACTIVO','PENDIENTE','INACTIVO') NOT NULL DEFAULT 'PENDIENTE'");

        $permissionId = DB::table('permisos')->where('codigo', 'orders.intake.create')->value('permiso_id');
        if ($permissionId) {
            DB::table('rol_permiso')->where('permiso_id', $permissionId)->delete();
            DB::table('permisos')->where('permiso_id', $permissionId)->update(['estado' => 'INACTIVO']);
        }
    }

    public function down(): void
    {
        DB::table('clientes')->where('estado', 'PENDIENTE')->update(['estado' => 'ACTIVO']);
        DB::statement("ALTER TABLE clientes MODIFY estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'");
        DB::table('permisos')->where('codigo', 'orders.intake.create')->update(['estado' => 'ACTIVO']);
    }
};
