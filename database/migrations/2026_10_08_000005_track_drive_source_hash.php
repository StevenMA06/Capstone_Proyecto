<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evidencias_drive_instalacion', function (Blueprint $table): void {
            $table->string('source_sha256', 64)->nullable();
        });
        DB::table('permisos')->where('codigo', 'technician.contractEvidence')->update(['estado' => 'INACTIVO']);
    }

    public function down(): void
    {
        Schema::table('evidencias_drive_instalacion', function (Blueprint $table): void {
            $table->dropColumn('source_sha256');
        });
        DB::table('permisos')->where('codigo', 'technician.contractEvidence')->update(['estado' => 'ACTIVO']);
    }
};
