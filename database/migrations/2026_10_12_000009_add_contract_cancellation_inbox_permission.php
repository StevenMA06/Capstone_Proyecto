<?php

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

        $definition = AccessCatalog::permissions()['contracts.cancel.inbox'];
        DB::table('permisos')->updateOrInsert(
            ['codigo' => 'contracts.cancel.inbox'],
            $definition + ['estado' => 'ACTIVO']
        );
        $inboxId = DB::table('permisos')->where('codigo', 'contracts.cancel.inbox')->value('permiso_id');

        // Preserve access for every role that was already allowed to review decisions.
        $reviewerRoleIds = DB::table('rol_permiso as rp')
            ->join('permisos as p', 'p.permiso_id', '=', 'rp.permiso_id')
            ->where('p.codigo', 'contracts.cancel.approve')->where('rp.permitido', true)
            ->pluck('rp.rol_id');
        foreach ($reviewerRoleIds as $roleId) {
            DB::table('rol_permiso')->updateOrInsert(
                ['rol_id' => $roleId, 'permiso_id' => $inboxId],
                ['permitido' => true]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos')) return;
        $permissionId = DB::table('permisos')->where('codigo', 'contracts.cancel.inbox')->value('permiso_id');
        if ($permissionId && Schema::hasTable('rol_permiso')) {
            DB::table('rol_permiso')->where('permiso_id', $permissionId)->delete();
        }
        DB::table('permisos')->where('codigo', 'contracts.cancel.inbox')->delete();
    }
};
