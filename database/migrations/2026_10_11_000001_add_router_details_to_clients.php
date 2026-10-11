<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable para conservar los clientes registrados antes de esta ampliación.
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('numero_referencia', 25)->nullable();
            $table->string('ip_router', 45)->nullable();
            $table->string('modelo_router', 100)->nullable();
            $table->string('usuario_router', 100)->nullable();
            $table->text('contrasena_router')->nullable();
            $table->string('ssid', 100)->nullable();
            $table->text('contrasena_wifi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn(['numero_referencia', 'ip_router', 'modelo_router', 'usuario_router', 'contrasena_router', 'ssid', 'contrasena_wifi']);
        });
    }
};
