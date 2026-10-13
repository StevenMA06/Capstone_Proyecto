<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Repositories\ContractPeriodRepository;
use App\Repositories\SaleRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CancelContractsForNonPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:cancel-for-non-payment
                            {--dry-run : Execute without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancela automáticamente contratos cuando tienen pagos marcados como IMPAGO por falta de pago';

    /**
     * Execute the console command.
     */
    public function handle(ContractPeriodRepository $periods, SaleRepository $sales): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: no se realizarán cambios reales');
            $this->newLine();
        }

        $this->info('📋 Buscando contratos ACTIVOS con pagos IMPAGO por falta de pago...');

        // Obtener contratos activos que tienen al menos un pago IMPAGO
        $contractsToCancel = Contract::where('estado', 'ACTIVO')
            ->whereHas('payments', function ($query) {
                $query->where('estado', 'IMPAGO');
            })
            ->with(['payments' => function ($query) {
                $query->where('estado', 'IMPAGO')
                    ->orderBy('fecha_vencimiento');
            }, 'client', 'currentPeriod'])
            ->get();

        if ($contractsToCancel->isEmpty()) {
            $this->line('   ✓ No hay contratos que deban cancelarse por falta de pago');

            return self::SUCCESS;
        }

        $this->warn("   ⚠ Se encontraron {$contractsToCancel->count()} contratos para cancelar:");
        $this->newLine();

        $cancelledCount = 0;

        foreach ($contractsToCancel as $contract) {
            $anulatedPayments = $contract->payments;
            $firstAnulated = $anulatedPayments->first();

            $this->line(sprintf(
                '   📄 Contrato #%d (%s):',
                $contract->getKey(),
                $contract->codigo
            ));
            $this->line(sprintf(
                '      Cliente: %s %s',
                $contract->client->nombres_razon_social ?? 'N/A',
                $contract->client->apellidos ?? ''
            ));
            $this->line(sprintf(
                '      Pagos IMPAGO: %d',
                $anulatedPayments->count()
            ));
            $this->line(sprintf(
                '      Primer pago IMPAGO: %s (vencimiento: %s)',
                $firstAnulated->codigo,
                $firstAnulated->fecha_vencimiento->format('d/m/Y')
            ));

            if (! $dryRun) {
                DB::transaction(function () use ($contract, $periods, $sales, $firstAnulated) {
                    // Cancelar el período actual
                    if ($contract->currentPeriod) {
                        $periods->cancel(
                            $contract->currentPeriod,
                            sprintf(
                                'Cancelado automáticamente por falta de pago. Pago IMPAGO: %s',
                                $firstAnulated->codigo
                            ),
                            'AUTOMATICO_FALTA_PAGO'
                        );
                    }

                    // Actualizar el contrato a CANCELADO
                    DB::table('contratos')
                        ->where('contrato_id', $contract->getKey())
                        ->update([
                            'estado' => 'CANCELADO',
                            'observaciones' => DB::raw(
                                "CONCAT(COALESCE(observaciones, ''), '\n[".now()->format('Y-m-d H:i:s').'] Cancelado automáticamente por falta de pago (pago '.
                                $firstAnulated->codigo." IMPAGO).')"
                            ),
                        ]);
                    if (Schema::hasTable('solicitudes_cancelacion_contrato')) {
                        DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $contract->getKey())
                            ->where('estado', 'PENDIENTE')->update([
                                'estado' => 'CERRADA', 'resuelto_en' => now('America/Lima'),
                                'decision_motivo' => 'La solicitud manual se cerró porque el sistema canceló el contrato por falta de pago.',
                            ]);
                    }
                    $sales->annul($contract->getKey());

                    // Anular todos los pagos pendientes, vencidos e impagos del contrato
                    $anulatedPaymentsCount = DB::table('pagos')
                        ->where('contrato_id', $contract->getKey())
                        ->whereIn('estado', ['PENDIENTE', 'VENCIDO', 'IMPAGO'])
                        ->update([
                            'estado' => 'ANULADO',
                            'observaciones' => DB::raw(
                                "CONCAT(COALESCE(observaciones, ''), '\n[".now()->format('Y-m-d H:i:s')."] Anulado automáticamente por cancelación del contrato.')"
                            ),
                        ]);

                    if ($anulatedPaymentsCount > 0) {
                        \Log::info("Anulados {$anulatedPaymentsCount} pagos del contrato {$contract->codigo} por cancelación automática");
                    }
                });

                $this->info('      ✅ Contrato cancelado automáticamente');
                $cancelledCount++;
            } else {
                $this->comment('      🔸 [DRY-RUN] Se cancelaría por falta de pago');
            }

            $this->newLine();
        }

        // ============================================================
        // RESUMEN
        // ============================================================

        $this->info('📊 RESUMEN:');
        $this->line(sprintf('   • Contratos cancelados por falta de pago: %d', $dryRun ? 0 : $cancelledCount));
        $this->line(sprintf('   • Contratos revisados: %d', $contractsToCancel->count()));

        if ($dryRun) {
            $this->newLine();
            $this->comment('🔸 Ejecución en modo DRY-RUN: no se realizaron cambios');
            $this->comment('   Ejecuta sin --dry-run para aplicar los cambios');
        }

        return self::SUCCESS;
    }
}
