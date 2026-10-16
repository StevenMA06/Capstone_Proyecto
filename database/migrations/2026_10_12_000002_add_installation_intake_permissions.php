<?php

use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $allPermissions = AccessCatalog::permissions();
        
        foreach (['orders.intake.create', 'orders.resources.request'] as $code) {
            // Verificar que el permiso existe en el catálogo
            if (!isset($allPermissions[$code])) {
                continue; // Saltar si el permiso no existe
            }
            
            $permission = $allPermissions[$code];
            DB::table('permisos')->updateOrInsert(['codigo' => $code], $permission + ['estado' => 'ACTIVO']);
        }

        $grant = [
            Role::OFFICE => ['orders.intake.create'],
            Role::TECHNICIAN => ['orders.resources.request'],
            Role::INSTALLATIONS => ['orders.resources.request'],
        ];
        foreach ($grant as $roleName => $codes) {
            $roleId = DB::table('roles')->where('nombre', $roleName)->value('rol_id');
            if (! $roleId) continue;
            foreach (DB::table('permisos')->whereIn('codigo', $codes)->pluck('permiso_id') as $permissionId) {
                DB::table('rol_permiso')->updateOrInsert(
                    ['rol_id' => $roleId, 'permiso_id' => $permissionId], ['permitido' => true]
                );
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permisos')->whereIn('codigo', ['orders.intake.create', 'orders.resources.request'])->pluck('permiso_id');
        DB::table('rol_permiso')->whereIn('permiso_id', $ids)->delete();
        DB::table('permisos')->whereIn('codigo', ['orders.intake.create', 'orders.resources.request'])->delete();
    }
};
