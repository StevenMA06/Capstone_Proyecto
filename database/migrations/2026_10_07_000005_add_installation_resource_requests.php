<?php

use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->id('solicitud_id');
            $table->unsignedBigInteger('pedido_id');
            $table->string('tipo', 10);
            $table->unsignedBigInteger('equipo_id')->nullable();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->string('estado', 25);
            $table->unsignedBigInteger('solicitado_por');
            $table->dateTime('solicitado_en');
            $table->unsignedBigInteger('resuelto_por')->nullable();
            $table->dateTime('resuelto_en')->nullable();
            $table->string('decision_observacion', 500)->nullable();
            $table->unsignedBigInteger('atendido_por')->nullable();
            $table->dateTime('atendido_en')->nullable();
            $table->unsignedBigInteger('cerrado_por')->nullable();
            $table->dateTime('cerrado_en')->nullable();
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion')->restrictOnDelete();
            $table->foreign('equipo_id')->references('equipo_id')->on('equipos')->restrictOnDelete();
            $table->foreign('material_id')->references('material_id')->on('materiales')->restrictOnDelete();
            foreach (['solicitado_por', 'resuelto_por', 'atendido_por', 'cerrado_por'] as $column) {
                $table->foreign($column)->references('usuario_id')->on('usuarios')->restrictOnDelete();
            }
            $table->index(['pedido_id', 'estado'], 'ix_solicitud_recurso_pedido_estado');
            $table->index(['estado', 'solicitado_en'], 'ix_solicitud_recurso_cola');
        });

        foreach (['warehouse.view', 'warehouse.fulfill', 'orders.resources.approve'] as $code) {
            DB::table('permisos')->updateOrInsert(['codigo' => $code], AccessCatalog::permissions()[$code] + ['estado' => 'ACTIVO']);
        }
        foreach ([Role::WAREHOUSE => ['warehouse.view', 'warehouse.fulfill'], Role::MANAGER => ['orders.resources.approve']] as $name => $codes) {
            $roleId = DB::table('roles')->where('nombre', $name)->value('rol_id');
            if (! $roleId) continue;
            foreach ($codes as $code) {
                $permissionId = DB::table('permisos')->where('codigo', $code)->value('permiso_id');
                DB::table('rol_permiso')->updateOrInsert(['rol_id' => $roleId, 'permiso_id' => $permissionId], ['permitido' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_solicitudes_recurso');
        $ids = DB::table('permisos')->whereIn('codigo', ['warehouse.view', 'warehouse.fulfill', 'orders.resources.approve'])->pluck('permiso_id');
        DB::table('rol_permiso')->whereIn('permiso_id', $ids)->delete();
        DB::table('permisos')->whereIn('permiso_id', $ids)->delete();
    }
};
