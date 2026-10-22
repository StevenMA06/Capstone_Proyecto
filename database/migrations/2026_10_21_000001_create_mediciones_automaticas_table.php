<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mediciones_automaticas', function (Blueprint $table): void {
            $table->id('medicion_id');
            $table->uuid('token')->unique();
            $table->string('indicador', 50);
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->unsignedBigInteger('intento')->nullable();
            $table->string('referencia_tipo', 40);
            $table->unsignedBigInteger('referencia_id');
            $table->unsignedBigInteger('usuario_id');
            $table->dateTime('inicio_en');
            $table->dateTime('ultima_interaccion_en');
            $table->dateTime('fin_en');
            $table->unsignedInteger('duracion_activa_segundos');
            $table->unsignedInteger('pausa_descartada_segundos')->default(0);
            $table->string('estado', 20)->default('COMPLETADA');
            $table->dateTime('registrado_en')->useCurrent();
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('usuario_id')->references('usuario_id')->on('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->index(['indicador', 'fin_en']);
            $table->index(['pedido_id', 'indicador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediciones_automaticas');
    }
};
