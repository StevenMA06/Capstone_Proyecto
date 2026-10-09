<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedido_reprogramaciones', function (Blueprint $table): void {
            $table->string('evidencia_ruta', 255)->nullable();
            $table->string('evidencia_nombre', 255)->nullable();
            $table->char('evidencia_sha256', 64)->nullable();
            $table->string('evidencia_tipo', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_reprogramaciones', function (Blueprint $table): void {
            $table->dropColumn(['evidencia_ruta', 'evidencia_nombre', 'evidencia_sha256', 'evidencia_tipo']);
        });
    }
};
