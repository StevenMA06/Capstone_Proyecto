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
        Schema::create('solicitudes_cancelacion_contrato', function (Blueprint $table): void {
            $table->id('solicitud_cancelacion_id');
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('solicitado_por');
            $table->dateTime('solicitado_en');
            $table->string('categoria', 60);
            $table->string('explicacion', 400);
            $table->string('evidencia_ruta', 255);
            $table->string('evidencia_nombre', 255);
            $table->char('evidencia_sha256', 64);
            $table->char('contrato_sha256', 64);
            $table->enum('estado', ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'CERRADA'])->default('PENDIENTE');
            $table->unsignedBigInteger('resuelto_por')->nullable();
            $table->dateTime('resuelto_en')->nullable();
            $table->string('decision_motivo', 500)->nullable();
            $table->foreign('contrato_id')->references('contrato_id')->on('contratos')->restrictOnDelete();
            $table->foreign('solicitado_por')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->foreign('resuelto_por')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->index(['contrato_id', 'estado'], 'ix_cancelacion_contrato_estado');
            $table->index(['estado', 'solicitado_en'], 'ix_cancelacion_estado_fecha');
        });

        foreach (['contracts.cancel.request', 'contracts.cancel.approve', 'contracts.cancel.evidence'] as $code) {
            DB::table('permisos')->updateOrInsert(
                ['codigo' => $code],
                AccessCatalog::permissions()[$code] + ['estado' => 'ACTIVO']
            );
        }

        $managerId = DB::table('roles')->where('nombre', Role::MANAGER)->value('rol_id');
        $permissionIds = DB::table('permisos')->whereIn('codigo', [
            'contracts.cancel.request', 'contracts.cancel.approve', 'contracts.cancel.evidence',
        ])->pluck('permiso_id');
        if ($managerId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('rol_permiso')->updateOrInsert(
                    ['rol_id' => $managerId, 'permiso_id' => $permissionId], ['permitido' => true]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cancelacion_contrato');
        $permissionIds = DB::table('permisos')->whereIn('codigo', [
            'contracts.cancel.request', 'contracts.cancel.approve', 'contracts.cancel.evidence',
        ])->pluck('permiso_id');
        DB::table('rol_permiso')->whereIn('permiso_id', $permissionIds)->delete();
        DB::table('permisos')->whereIn('codigo', [
            'contracts.cancel.request', 'contracts.cancel.approve', 'contracts.cancel.evidence',
        ])->delete();
    }
};
