<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega campos para tracking de notificaciones automáticas de pagos
     * (evitar duplicación de correos).
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            // Timestamps para tracking de envío de correos
            $table->dateTime('recordatorio_previo_enviado_en')->nullable()
                ->after('constancia_ruta')
                ->comment('Fecha de envío del correo 7 días antes del vencimiento');

            $table->dateTime('recordatorio_vencido_enviado_en')->nullable()
                ->after('recordatorio_previo_enviado_en')
                ->comment('Fecha de envío del correo 5 días después del vencimiento');

            $table->dateTime('notificacion_anulacion_enviada_en')->nullable()
                ->after('recordatorio_vencido_enviado_en')
                ->comment('Fecha de envío del correo de anulación/cancelación');

            // Índices para búsquedas eficientes en commands
            $table->index('recordatorio_previo_enviado_en', 'ix_pago_recordatorio_previo');
            $table->index('recordatorio_vencido_enviado_en', 'ix_pago_recordatorio_vencido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropIndex('ix_pago_recordatorio_previo');
            $table->dropIndex('ix_pago_recordatorio_vencido');
            
            $table->dropColumn([
                'recordatorio_previo_enviado_en',
                'recordatorio_vencido_enviado_en',
                'notificacion_anulacion_enviada_en',
            ]);
        });
    }
};
