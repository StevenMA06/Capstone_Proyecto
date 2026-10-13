<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos_instalacion', function (Blueprint $table): void {
            $table->string('tipo_instalacion', 80)->nullable();
            $table->string('tipo_documento_cliente', 20)->nullable();
            $table->string('documento_cliente', 20)->nullable();
            $table->string('solicitante_nombre', 180)->nullable();
            $table->string('telefono_contacto', 25)->nullable();
            $table->string('telefono_alternativo', 25)->nullable();
            $table->string('correo_contacto', 150)->nullable();
            $table->string('direccion_solicitada', 255)->nullable();
            $table->string('referencia_direccion_solicitada', 255)->nullable();
            $table->string('ubicacion_compartida', 500)->nullable();
            $table->string('plan_solicitado', 180)->nullable();
            $table->decimal('precio_plan_solicitado', 12, 2)->nullable();
            $table->decimal('costo_instalacion_solicitado', 12, 2)->nullable();
            $table->date('disponibilidad_fecha')->nullable();
            $table->time('disponibilidad_desde')->nullable();
            $table->time('disponibilidad_hasta')->nullable();
            $table->string('estado_cobertura', 30)->nullable();
            $table->string('detalle_cobertura', 500)->nullable();
            $table->string('estado_documentacion', 30)->default('PENDIENTE_INSTALACION');
            $table->unsignedBigInteger('tecnico_secundario_id')->nullable();
            $table->dateTime('confirmado_por_tecnico_secundario_en')->nullable();
            $table->index(['documento_cliente', 'estado'], 'ix_pedido_documento_estado');
            $table->foreign('tecnico_secundario_id', 'fk_pedido_tecnico_secundario')
                ->references('empleado_id')->on('empleados')->restrictOnDelete();
        });

        // New requests exist before Mikrowisp and the signed contract are created.
        DB::statement('ALTER TABLE pedidos_instalacion MODIFY contrato_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE pedidos_instalacion MODIFY cliente_id BIGINT UNSIGNED NULL');

        Schema::table('pedido_solicitudes_recursos', function (Blueprint $table): void {
            $table->boolean('incluye_equipo')->default(false);
            $table->boolean('incluye_material')->default(false);
        });
    }

    public function down(): void
    {
        // Preserve orders created without a formal client/contract; they cannot be rolled back safely.
        Schema::table('pedidos_instalacion', function (Blueprint $table): void {
            $table->dropIndex('ix_pedido_documento_estado');
            $table->dropForeign('fk_pedido_tecnico_secundario');
            $table->dropColumn([
                'tipo_instalacion', 'tipo_documento_cliente', 'documento_cliente', 'solicitante_nombre', 'telefono_contacto',
                'telefono_alternativo', 'correo_contacto', 'direccion_solicitada', 'referencia_direccion_solicitada',
                'ubicacion_compartida', 'plan_solicitado', 'precio_plan_solicitado',
                'costo_instalacion_solicitado', 'disponibilidad_fecha', 'disponibilidad_desde',
                'disponibilidad_hasta', 'estado_cobertura', 'detalle_cobertura', 'estado_documentacion', 'tecnico_secundario_id',
                'confirmado_por_tecnico_secundario_en',
            ]);
        });
        Schema::table('pedido_solicitudes_recursos', function (Blueprint $table): void {
            $table->dropColumn(['incluye_equipo', 'incluye_material']);
        });
    }
};
