<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el nuevo estado NO_PAGADO para distinguir entre:
     * - NO_PAGADO: pago vencido automáticamente (7 días sin pagar)
     * - ANULADO: pago cancelado manualmente por error administrativo
     */
    public function up(): void
    {
        // No necesitamos modificar la estructura de la tabla
        // Solo documentamos que ahora usaremos NO_PAGADO como valor adicional
        // El campo 'estado' ya es VARCHAR, así que acepta cualquier valor
        
        // Actualizar registros existentes que estén ANULADO por sistema
        // Los que fueron marcados por falta de pago deberían ser NO_PAGADO
        DB::statement("
            UPDATE pagos 
            SET estado = 'NO_PAGADO' 
            WHERE estado = 'ANULADO'
            AND observaciones LIKE '%Anulado automáticamente%'
        ");
    }

    /**
     * Revertir: volver NO_PAGADO a ANULADO
     */
    public function down(): void
    {
        DB::statement("
            UPDATE pagos 
            SET estado = 'ANULADO' 
            WHERE estado = 'NO_PAGADO'
        ");
    }
};
