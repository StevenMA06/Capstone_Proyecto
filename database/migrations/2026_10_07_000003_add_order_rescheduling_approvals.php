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
        Schema::create('pedido_reprogramaciones', function (Blueprint $table): void {
            $table->id('reprogramacion_id');
            $table->unsignedBigInteger('pedido_id');
            $table->dateTime('fecha_anterior');
            $table->dateTime('fecha_nueva');
            $table->string('franja_anterior', 100)->nullable();
            $table->string('franja_nueva', 100);
            $table->string('canal_coordinacion', 30);
            $table->string('detalle_coordinacion', 500)->nullable();
            $table->string('motivo_codigo', 40);
            $table->string('motivo_detalle', 500);
            $table->unsignedBigInteger('solicitado_por');
            $table->dateTime('solicitado_en');
            $table->string('estado', 20)->default('SOLICITADA');
            $table->unsignedBigInteger('resuelto_por')->nullable();
            $table->dateTime('resuelto_en')->nullable();
            $table->string('decision_observacion', 500)->nullable();
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion')->restrictOnDelete();
            $table->foreign('solicitado_por')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->foreign('resuelto_por')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->index(['pedido_id', 'estado'], 'ix_reprogramacion_pedido_estado');
        });

        $definition = AccessCatalog::permissions()['orders.approve'];
        DB::table('permisos')->updateOrInsert(['codigo' => 'orders.approve'], $definition + ['estado' => 'ACTIVO']);
        $permissionId = DB::table('permisos')->where('codigo', 'orders.approve')->value('permiso_id');
        $managerId = DB::table('roles')->where('nombre', Role::MANAGER)->value('rol_id');
        if ($managerId && $permissionId) {
            DB::table('rol_permiso')->updateOrInsert(['rol_id' => $managerId, 'permiso_id' => $permissionId], ['permitido' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_reprogramaciones');
        $permissionId = DB::table('permisos')->where('codigo', 'orders.approve')->value('permiso_id');
        if ($permissionId) {
            DB::table('rol_permiso')->where('permiso_id', $permissionId)->delete();
            DB::table('permisos')->where('permiso_id', $permissionId)->delete();
        }
    }
};
