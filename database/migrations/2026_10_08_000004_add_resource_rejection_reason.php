<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->string('decision_motivo_codigo', 40)->nullable()->after('decision_observacion');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_solicitudes_recurso', function (Blueprint $table): void {
            $table->dropColumn('decision_motivo_codigo');
        });
    }
};
