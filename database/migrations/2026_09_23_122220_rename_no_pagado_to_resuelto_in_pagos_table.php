<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar el ENUM para incluir RESUELTO y eliminar NO_PAGADO
        DB::statement("ALTER TABLE pagos MODIFY COLUMN estado ENUM('PENDIENTE', 'REALIZADO', 'VENCIDO', 'ANULADO', 'RESUELTO') NOT NULL DEFAULT 'PENDIENTE'");
        
        // Actualizar todos los registros que tengan NO_PAGADO a RESUELTO (por si acaso existen)
        DB::table('pagos')
            ->where('estado', 'NO_PAGADO')
            ->update(['estado' => 'RESUELTO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: cambiar RESUELTO de vuelta a NO_PAGADO
        DB::table('pagos')
            ->where('estado', 'RESUELTO')
            ->update(['estado' => 'NO_PAGADO']);
            
        DB::statement("ALTER TABLE pagos MODIFY COLUMN estado ENUM('PENDIENTE', 'REALIZADO', 'VENCIDO', 'ANULADO', 'NO_PAGADO') NOT NULL DEFAULT 'PENDIENTE'");
    }
};
