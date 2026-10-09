<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos_instalacion', function (Blueprint $table): void {
            $table->string('fase_flujo', 40)->nullable();
            $table->string('canal_coordinacion', 30)->nullable();
            $table->string('detalle_coordinacion', 500)->nullable();
            $table->unsignedBigInteger('coordinado_por')->nullable();
            $table->dateTime('coordinado_en')->nullable();
            $table->string('franja_horaria', 100)->nullable();
            $table->unsignedBigInteger('asignado_por')->nullable();
            $table->dateTime('asignado_en')->nullable();
            $table->dateTime('confirmado_por_tecnico_en')->nullable();
            $table->dateTime('notificado_en')->nullable();
            $table->index(['fase_flujo', 'fecha_programada'], 'ix_pedido_fase_fecha');
        });

        Schema::create('incidencias_instalacion', function (Blueprint $table): void {
            $table->id('incidencia_id');
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedBigInteger('registrado_por');
            $table->string('tipo', 30);
            $table->string('recurso_tipo', 20);
            $table->unsignedBigInteger('recurso_id')->nullable();
            $table->string('recurso_descripcion', 200);
            $table->decimal('cantidad', 12, 3)->nullable();
            $table->string('causa', 500);
            $table->text('observacion');
            $table->string('evidencia_ruta', 500)->nullable();
            $table->string('evidencia_nombre', 255)->nullable();
            $table->dateTime('registrado_en');
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion');
            $table->foreign('registrado_por')->references('usuario_id')->on('usuarios');
            $table->index(['pedido_id', 'registrado_en']);
        });

        Schema::create('evidencias_drive_instalacion', function (Blueprint $table): void {
            $table->id('sincronizacion_id');
            $table->unsignedBigInteger('pedido_id');
            $table->string('tipo', 30);
            $table->unsignedBigInteger('evidencia_id');
            $table->string('drive_file_id', 200)->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('error', 500)->nullable();
            $table->dateTime('confirmado_en')->nullable();
            $table->timestamps();
            $table->unique(['tipo', 'evidencia_id']);
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias_drive_instalacion');
        Schema::dropIfExists('incidencias_instalacion');
        Schema::table('pedidos_instalacion', function (Blueprint $table): void {
            $table->dropIndex('ix_pedido_fase_fecha');
            $table->dropColumn(['fase_flujo', 'canal_coordinacion', 'detalle_coordinacion', 'coordinado_por',
                'coordinado_en', 'franja_horaria', 'asignado_por', 'asignado_en',
                'confirmado_por_tecnico_en', 'notificado_en']);
        });
    }
};
