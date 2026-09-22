<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crea la tabla periodos_contractuales para mantener historial de contratos,
     * especialmente útil para reactivaciones que conservan el mismo código.
     */
    public function up(): void
    {
        Schema::create('periodos_contractuales', function (Blueprint $table): void {
            $table->id('periodo_id');
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedSmallInteger('numero_periodo')->comment('1, 2, 3... secuencial por contrato');
            $table->date('fecha_inicio_periodo');
            $table->date('fecha_fin_periodo');
            $table->unsignedSmallInteger('duracion_meses_periodo');
            $table->enum('estado_periodo', ['ACTIVO', 'CANCELADO', 'FINALIZADO'])->default('ACTIVO');
            $table->string('motivo_cancelacion', 500)->nullable();
            $table->dateTime('fecha_cancelacion')->nullable();
            $table->unsignedBigInteger('cancelado_por')->nullable()->comment('FK a usuarios');
            $table->enum('origen_cancelacion', ['MANUAL', 'AUTOMATICO_FALTA_PAGO'])->nullable();
            $table->dateTime('creado_en')->useCurrent();
            $table->dateTime('actualizado_en')->useCurrent()->useCurrentOnUpdate();

            // Foreign keys
            $table->foreign('contrato_id')
                ->references('contrato_id')
                ->on('contratos')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('cancelado_por')
                ->references('usuario_id')
                ->on('usuarios')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Indexes
            $table->index(['contrato_id', 'numero_periodo'], 'ix_periodo_contrato_numero');
            $table->index(['contrato_id', 'estado_periodo'], 'ix_periodo_contrato_estado');
            $table->index('fecha_inicio_periodo', 'ix_periodo_fecha_inicio');
            $table->index('fecha_fin_periodo', 'ix_periodo_fecha_fin');

            // Unique constraint: un contrato no puede tener dos períodos con el mismo número
            $table->unique(['contrato_id', 'numero_periodo'], 'uq_contrato_periodo_numero');

            // Check constraints (aplicados a nivel de aplicación en Laravel)
            // - duracion_meses_periodo > 0
            // - fecha_fin_periodo >= fecha_inicio_periodo
            // - Si estado_periodo = CANCELADO: motivo_cancelacion NOT NULL, fecha_cancelacion NOT NULL, origen_cancelacion NOT NULL
        });

        // Agregar columna periodo_actual a contratos
        Schema::table('contratos', function (Blueprint $table): void {
            $table->unsignedBigInteger('periodo_actual')->nullable()
                ->after('estado')
                ->comment('FK al período contractual activo actual');

            $table->foreign('periodo_actual')
                ->references('periodo_id')
                ->on('periodos_contractuales')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('periodo_actual', 'ix_contrato_periodo_actual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primero eliminar la FK en contratos
        Schema::table('contratos', function (Blueprint $table): void {
            $table->dropForeign(['periodo_actual']);
            $table->dropIndex('ix_contrato_periodo_actual');
            $table->dropColumn('periodo_actual');
        });

        // Luego eliminar la tabla
        Schema::dropIfExists('periodos_contractuales');
    }
};
