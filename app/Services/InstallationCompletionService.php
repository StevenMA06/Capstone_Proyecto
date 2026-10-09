<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InstallationCompletionService
{
    /** Close an in-progress installation once its signed contract and final photo are verified in Drive. */
    public function completeIfReady(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $order = DB::table('pedidos_instalacion')->where('pedido_id', $orderId)->lockForUpdate()->first();
            if (! $order || $order->estado !== 'EN_PROCESO') return false;

            $execution = DB::table('ejecuciones_instalacion')->where('pedido_id', $orderId)->lockForUpdate()->first();
            if (! $execution || $execution->estado !== 'EN_PROCESO' || ! $execution->inicio_en || $execution->fin_en) return false;

            $contract = DB::table('contratos')->where('contrato_id', $order->contrato_id)->first();
            if (! $contract || $contract->firma_revision !== 'VERIFICADA' || ! $contract->pdf_contrato_ruta
                || ! Storage::disk('local')->exists($contract->pdf_contrato_ruta)) return false;

            $contractHash = hash_file('sha256', Storage::disk('local')->path($contract->pdf_contrato_ruta));
            $contractConfirmed = DB::table('evidencias_drive_instalacion')->where('pedido_id', $orderId)
                ->where('tipo', 'CONTRATO_VALIDADO')->where('evidencia_id', $contract->contrato_id)
                ->where('estado', 'CONFIRMADO')->whereNotNull('drive_file_id')
                ->where('source_sha256', $contractHash)->exists();
            if (! $contractConfirmed) return false;

            $photos = DB::table('evidencias_drive_instalacion as drive')
                ->join('evidencias_instalacion as evidence', function ($join): void {
                    $join->on('evidence.evidencia_id', '=', 'drive.evidencia_id')
                        ->on('evidence.pedido_id', '=', 'drive.pedido_id');
                })
                ->where('drive.pedido_id', $orderId)->where('drive.tipo', 'FOTO')
                ->where('drive.estado', 'CONFIRMADO')->whereNotNull('drive.drive_file_id')
                ->where('evidence.tipo', 'FOTOGRAFIA_INSTALACION')
                ->get(['evidence.ruta_archivo', 'drive.source_sha256']);
            $photoConfirmed = $photos->contains(function ($photo): bool {
                if (! Storage::disk('local')->exists($photo->ruta_archivo)) return false;
                $path = Storage::disk('local')->path($photo->ruta_archivo);
                return is_file($path) && $photo->source_sha256 && hash_file('sha256', $path) === $photo->source_sha256;
            });
            if (! $photoConfirmed) return false;

            $time = now('America/Lima');
            DB::table('ejecuciones_instalacion')->where('ejecucion_id', $execution->ejecucion_id)
                ->update(['estado' => 'COMPLETADA', 'fin_en' => $time, 'actualizado_en' => $time]);
            DB::table('pedidos_instalacion')->where('pedido_id', $orderId)->update([
                'estado' => 'COMPLETADO', 'fase_flujo' => $order->fase_flujo ? 'COMPLETADO' : null,
                'actualizado_en' => $time,
            ]);

            if (Schema::hasTable('pedido_historial_eventos')) {
                DB::table('pedido_historial_eventos')->insert([
                    'pedido_id' => $orderId, 'usuario_id' => null, 'tipo' => 'CIERRE_AUTOMATICO',
                    'motivo' => 'Cierre automático tras confirmar en Google Drive la fotografía de instalación y el contrato firmado verificado.',
                    'contexto' => json_encode(['origen' => 'sistema', 'foto_drive' => true, 'contrato_drive' => true]),
                    'registrado_en' => $time,
                ]);
            }

            Log::info('Instalación cerrada automáticamente tras validar sus evidencias en Drive.', ['pedido_id' => $orderId]);
            return true;
        }, 3);
    }

    public function tryCompleteAfterDriveSync(int $orderId): void
    {
        try {
            $this->completeIfReady($orderId);
        } catch (\Throwable $error) {
            Log::error('No se pudo ejecutar el cierre automático de la instalación.', [
                'pedido_id' => $orderId, 'error' => $error->getMessage(),
            ]);
        }
    }
}
