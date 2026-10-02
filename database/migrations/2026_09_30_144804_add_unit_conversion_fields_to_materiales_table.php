<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materiales', function (Blueprint $table) {
            // Renombrar unidad_medida a unidad_base
            $table->renameColumn('unidad_medida', 'unidad_base');
            
            // Agregar campos nuevos
            $table->string('unidad_empaque', 30)->nullable()->after('unidad_base');
            $table->decimal('factor_conversion', 10, 3)->nullable()->after('unidad_empaque');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materiales', function (Blueprint $table) {
            // Eliminar campos agregados
            $table->dropColumn(['unidad_empaque', 'factor_conversion']);
            
            // Renombrar de vuelta
            $table->renameColumn('unidad_base', 'unidad_medida');
        });
    }
};
