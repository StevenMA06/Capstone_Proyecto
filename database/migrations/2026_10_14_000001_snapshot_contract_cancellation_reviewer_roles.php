<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudes_cancelacion_contrato', 'resuelto_roles')) {
            Schema::table('solicitudes_cancelacion_contrato', function (Blueprint $table): void {
                $table->text('resuelto_roles')->nullable()->after('resuelto_por');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes_cancelacion_contrato', 'resuelto_roles')) {
            Schema::table('solicitudes_cancelacion_contrato', function (Blueprint $table): void {
                $table->dropColumn('resuelto_roles');
            });
        }
    }
};
