<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PASO 1: Modificar ENUM para agregar 'IMPAGO' y mantener 'RESUELTO' temporalmente
        DB::statement("
            ALTER TABLE pagos 
            MODIFY COLUMN estado ENUM('PENDIENTE', 'REALIZADO', 'VENCIDO', 'RESUELTO', 'IMPAGO', 'ANULADO') 
            NOT NULL DEFAULT 'PENDIENTE'
        ");

        // PASO 2: Renombrar 'RESUELTO' a 'IMPAGO' en registros existentes
        DB::statement("UPDATE pagos SET estado = 'IMPAGO' WHERE estado = 'RESUELTO'");

        // PASO 3: Remover 'RESUELTO' del ENUM
        DB::statement("
            ALTER TABLE pagos 
            MODIFY COLUMN estado ENUM('PENDIENTE', 'REALIZADO', 'VENCIDO', 'IMPAGO', 'ANULADO') 
            NOT NULL DEFAULT 'PENDIENTE'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PASO 1: Agregar 'RESUELTO' de vuelta al ENUM
        DB::statement("
            ALTER TABLE pagos 
            MODIFY COLUMN estado ENUM('PENDIENTE', 'REALIZADO', 'VENCIDO', 'RESUELTO', 'IMPAGO', 'ANULADO') 
            NOT NULL DEFAULT 'PENDIENTE'
        ");

        // PASO 2: Revertir 'IMPAGO' a 'RESUELTO' en registros existentes
        DB::statement("UPDATE pagos SET estado = 'RESUELTO' WHERE estado = 'IMPAGO'");

        // PASO 3: Restaurar ENUM original sin 'IMPAGO'
        DB::statement("
            ALTER TABLE pagos 
            MODIFY COLUMN estado ENUM('PENDIENTE', 'REALIZADO', 'VENCIDO', 'RESUELTO', 'ANULADO') 
            NOT NULL DEFAULT 'PENDIENTE'
        ");
    }
};
