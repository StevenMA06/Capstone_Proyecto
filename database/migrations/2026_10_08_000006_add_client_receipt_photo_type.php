<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE evidencias_instalacion MODIFY COLUMN tipo ENUM('FOTOGRAFIA_INSTALACION','COMPROBANTE_CLIENTE','OTRA') NOT NULL DEFAULT 'FOTOGRAFIA_INSTALACION'");
    }

    public function down(): void
    {
        DB::table('evidencias_instalacion')->where('tipo', 'COMPROBANTE_CLIENTE')->update(['tipo' => 'OTRA']);
        DB::statement("ALTER TABLE evidencias_instalacion MODIFY COLUMN tipo ENUM('FOTOGRAFIA_INSTALACION','OTRA') NOT NULL DEFAULT 'FOTOGRAFIA_INSTALACION'");
    }
};
