<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->string('equipo_tipo_solicitado', 80)->nullable()->after('equipo_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->dropColumn('equipo_tipo_solicitado');
        });
    }
};
