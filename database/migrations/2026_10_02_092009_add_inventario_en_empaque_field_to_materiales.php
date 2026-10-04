<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * IMPORTANTE: Esta migración NO toca los datos existentes.
     * Solo añade el campo y marca automáticamente los materiales que tienen empaque configurado.
     * 
     * CONCEPTO CORRECTO:
     * - Si un material tiene unidad_empaque Y factor_conversion configurados → inventario_en_empaque = TRUE
     * - El stock_actual se interpreta como "cantidad de empaques" (ej: 30 cajas)
     * - Si NO tiene empaque → inventario_en_empaque = FALSE
     * - El stock_actual se interpreta como "unidades sueltas" (ej: 100.5 metros)
     */
    public function up(): void
    {
        if (Schema::hasColumn('materiales', 'inventario_en_empaque')) return;
        Schema::table('materiales', function (Blueprint $table) {
            $table->boolean('inventario_en_empaque')->default(false)->after('factor_conversion');
        });

        // Marcar como TRUE solo los que TIENEN empaque configurado
        // NO tocamos el stock_actual, ese valor YA es correcto (son cajas, no unidades)
        DB::statement("
            UPDATE materiales 
            SET inventario_en_empaque = TRUE
            WHERE unidad_empaque IS NOT NULL 
              AND factor_conversion IS NOT NULL 
              AND factor_conversion > 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materiales', function (Blueprint $table) {
            $table->dropColumn('inventario_en_empaque');
        });
    }
};
