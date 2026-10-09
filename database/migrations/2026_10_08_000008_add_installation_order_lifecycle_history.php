<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedido_historial_eventos', function (Blueprint $table): void {
            $table->id('evento_id');
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('tipo', 30);
            $table->string('motivo', 500);
            $table->json('contexto')->nullable();
            $table->dateTime('registrado_en');
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos_instalacion')->restrictOnDelete();
            $table->foreign('usuario_id')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->index(['pedido_id', 'registrado_en'], 'ix_pedido_eventos_fecha');
        });

        DB::table('pedidos_instalacion')->where('estado', 'CANCELADO')->orderBy('pedido_id')->chunkById(250, function ($orders): void {
            foreach ($orders as $order) {
                DB::table('pedido_historial_eventos')->insert([
                    'pedido_id' => $order->pedido_id,
                    'usuario_id' => null,
                    'tipo' => 'CANCELACION',
                    'motivo' => 'Cancelación previa al historial detallado; el usuario y el motivo no quedaron registrados.',
                    'contexto' => json_encode(['historial_previo' => true]),
                    'registrado_en' => $order->actualizado_en ?? now('America/Lima'),
                ]);
            }
        }, 'pedido_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_historial_eventos');
    }
};
