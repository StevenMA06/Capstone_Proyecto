<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InstallationCompletionService
{
    /** Show archived field files only after a completed install has both Drive-verified proofs. */
    public function hasVerifiedCompletionEvidence(int $contractId): bool
    {
        $contract = DB::table('contratos')->where('contrato_id', $contractId)->first([
            'contrato_id', 'firma_revision', 'pdf_contrato_ruta',
        ]);
        if (! $contract || $contract->firma_revision !== 'VERIFICADA' || ! $contract->pdf_contrato_ruta
            || ! Storage::disk('local')->exists($contract->pdf_contrato_ruta)) {
            return false;
        }

        $contractHash = hash_file('sha256', Storage::disk('local')->path($contract->pdf_contrato_ruta));
        $orders = DB::table('pedidos_instalacion')->where('contrato_id', $contractId)
            ->where('estado', 'COMPLETADO')->pluck('pedido_id');
        foreach ($orders as $orderId) {
            $photoConfirmed = DB::table('evidencias_drive_instalacion as drive')
                ->join('evidencias_instalacion as evidence', function ($join): void {
                    $join->on('evidence.evidencia_id', '=', 'drive.evidencia_id')
                        ->on('evidence.pedido_id', '=', 'drive.pedido_id');
                })
                ->where('drive.pedido_id', $orderId)->where('drive.tipo', 'FOTO')
                ->where('drive.estado', 'CONFIRMADO')->whereNotNull('drive.drive_file_id')
                ->where('evidence.tipo', 'FOTOGRAFIA_INSTALACION')
                ->get(['evidence.ruta_archivo', 'drive.source_sha256'])
                ->contains(function ($photo): bool {
                    if (! Storage::disk('local')->exists($photo->ruta_archivo)) return false;
                    $path = Storage::disk('local')->path($photo->ruta_archivo);
                    return is_file($path) && $photo->source_sha256 && hash_file('sha256', $path) === $photo->source_sha256;
                });
            if (! $photoConfirmed) continue;

            $contractConfirmed = DB::table('evidencias_drive_instalacion')
                ->where('pedido_id', $orderId)->where('tipo', 'CONTRATO_VALIDADO')
                ->where('evidencia_id', $contractId)->where('estado', 'CONFIRMADO')
                ->whereNotNull('drive_file_id')->where('source_sha256', $contractHash)->exists();
            if ($contractConfirmed) return true;
        }

        return false;
    }

    /** Close field work and activate the client only after both required Drive evidences are validated. */
    public function completeIfReady(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $order = DB::table('pedidos_instalacion')->where('pedido_id', $orderId)->lockForUpdate()->first();
            if (! $order || ! in_array($order->estado, ['EN_PROCESO', 'COMPLETADO'], true)) return false;

            $execution = DB::table('ejecuciones_instalacion')->where('pedido_id', $orderId)->lockForUpdate()->first();
            if (! $execution || ! $execution->inicio_en) return false;

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

            $contractReady = false;
            if ($order->contrato_id) {
                $contract = DB::table('contratos')->where('contrato_id', $order->contrato_id)->first();
                if ($contract && $contract->firma_revision === 'VERIFICADA' && $contract->pdf_contrato_ruta
                    && Storage::disk('local')->exists($contract->pdf_contrato_ruta)) {
                    $contractHash = hash_file('sha256', Storage::disk('local')->path($contract->pdf_contrato_ruta));
                    $contractReady = DB::table('evidencias_drive_instalacion')->where('pedido_id', $orderId)
                        ->where('tipo', 'CONTRATO_VALIDADO')->where('evidencia_id', $contract->contrato_id)
                        ->where('estado', 'CONFIRMADO')->whereNotNull('drive_file_id')
                        ->where('source_sha256', $contractHash)->exists();
                }
            }
            if (! $contractReady) return false;

            $time = now('America/Lima');
            $newlyCompleted = $order->estado !== 'COMPLETADO' || $execution->estado !== 'COMPLETADA';
            if ($newlyCompleted) {
                if ($execution->estado !== 'EN_PROCESO' || $execution->fin_en) return false;
                DB::table('ejecuciones_instalacion')->where('ejecucion_id', $execution->ejecucion_id)
                    ->update(['estado' => 'COMPLETADA', 'fin_en' => $time, 'actualizado_en' => $time]);
                DB::table('pedidos_instalacion')->where('pedido_id', $orderId)->update([
                    'estado' => 'COMPLETADO', 'fase_flujo' => $order->fase_flujo ? 'COMPLETADO' : null,
                    'estado_documentacion' => 'COMPLETA',
                    'actualizado_en' => $time,
                ]);
                if (Schema::hasTable('pedido_historial_eventos')) {
                    DB::table('pedido_historial_eventos')->insert([
                        'pedido_id' => $orderId, 'usuario_id' => null, 'tipo' => 'CIERRE_AUTOMATICO',
                        'motivo' => 'Instalación y documentación cerradas automáticamente tras validar la foto de campo y el contrato firmado en Google Drive.',
                        'contexto' => json_encode(['origen' => 'sistema', 'foto_drive' => true, 'contrato_drive' => true]),
                        'registrado_en' => $time,
                    ]);
                }
            } else {
                DB::table('pedidos_instalacion')->where('pedido_id', $orderId)
                    ->update(['estado_documentacion' => 'COMPLETA', 'actualizado_en' => $time]);
            }

            $activated = false;
            if ($contractReady && $order->cliente_id) {
                $activated = DB::table('clientes')->where('cliente_id', $order->cliente_id)
                    ->where('estado', 'PENDIENTE')->update(['estado' => 'ACTIVO', 'actualizado_en' => $time]) > 0;
                if ($activated && Schema::hasTable('pedido_historial_eventos')) {
                    DB::table('pedido_historial_eventos')->insert([
                        'pedido_id' => $orderId, 'usuario_id' => null, 'tipo' => 'CLIENTE_ACTIVADO_AUTO',
                        'motivo' => 'El sistema activó al cliente después de validar la instalación, la firma del contrato y su copia confirmada en Google Drive.',
                        'contexto' => json_encode(['origen' => 'sistema', 'cliente_id' => $order->cliente_id]),
                        'registrado_en' => $time,
                    ]);
                }
            }

            if ($newlyCompleted || $activated) {
                Log::info('El sistema actualizó automáticamente la instalación y el estado del cliente.', [
                    'pedido_id' => $orderId, 'instalacion_completada' => $newlyCompleted, 'cliente_activado' => $activated,
                ]);
            }
            return $newlyCompleted || $activated;
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
