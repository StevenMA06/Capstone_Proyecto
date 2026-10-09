<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedido_solicitudes_recursos', function (Blueprint $table): void {
            $table->id('solicitud_grupo_id');
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedBigInteger('solicitado_por');
            $table->dateTime('solicitado_en');
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion')->restrictOnDelete();
            $table->foreign('solicitado_por')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->index(['pedido_id', 'solicitado_en'], 'ix_solicitud_recursos_pedido_fecha');
        });

        Schema::table('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->unsignedBigInteger('solicitud_grupo_id')->nullable()->after('pedido_id');
            $table->foreign('solicitud_grupo_id', 'fk_solicitud_recurso_grupo')
                ->references('solicitud_grupo_id')->on('pedido_solicitudes_recursos')->restrictOnDelete();
            $table->index(['solicitud_grupo_id', 'estado'], 'ix_solicitud_recurso_grupo_estado');
        });

        // Historical rows remain separate because the old flow did not store a reliable batch ID.
        DB::table('pedido_solicitudes_recurso')->orderBy('solicitud_id')->chunkById(250, function ($rows): void {
            foreach ($rows as $row) {
                $groupId = DB::table('pedido_solicitudes_recursos')->insertGetId([
                    'pedido_id' => $row->pedido_id,
                    'solicitado_por' => $row->solicitado_por,
                    'solicitado_en' => $row->solicitado_en,
                ]);
                DB::table('pedido_solicitudes_recurso')->where('solicitud_id', $row->solicitud_id)
                    ->update(['solicitud_grupo_id' => $groupId]);
            }
        }, 'solicitud_id');
    }

    public function down(): void
    {
        Schema::table('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->dropForeign('fk_solicitud_recurso_grupo');
            $table->dropIndex('ix_solicitud_recurso_grupo_estado');
            $table->dropColumn('solicitud_grupo_id');
        });
        Schema::dropIfExists('pedido_solicitudes_recursos');
    }
};
