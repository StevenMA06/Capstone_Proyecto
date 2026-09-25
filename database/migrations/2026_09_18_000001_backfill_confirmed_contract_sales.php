<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contratos as c')
            ->leftJoin('ventas as v', 'v.contrato_id', '=', 'c.contrato_id')
            ->whereNull('v.venta_id')
            ->where('c.firma_revision', 'VERIFICADA')
            ->whereNotNull('c.firma_validada_en')
            ->whereIn('c.estado', ['ACTIVO', 'FINALIZADO', 'CANCELADO'])
            ->select('c.contrato_id', 'c.cliente_id', 'c.responsable_id', 'c.firma_validada_en', 'c.precio_contratado', 'c.estado')
            ->chunkById(200, function ($contracts): void {
                DB::table('ventas')->insert($contracts->map(fn ($contract) => [
                    'contrato_id' => $contract->contrato_id,
                    'cliente_id' => $contract->cliente_id,
                    'responsable_id' => $contract->responsable_id,
                    'fecha_venta' => $contract->firma_validada_en,
                    'monto' => $contract->precio_contratado,
                    'estado' => $contract->estado === 'CANCELADO' ? 'ANULADA' : 'CONFIRMADA',
                ])->all());
            }, 'c.contrato_id', 'contrato_id');
    }

    public function down(): void
    {
        // Las ventas son registros comerciales: revertir la migración no debe borrarlas.
    }
};
