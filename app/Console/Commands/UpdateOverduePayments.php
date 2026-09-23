<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateOverduePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:update-overdue
                            {--dry-run : Execute without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza automáticamente los estados de pagos: PENDIENTE → VENCIDO y VENCIDO → IMPAGO según las reglas de negocio';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today = now()->startOfDay();

        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: no se realizarán cambios reales');
            $this->newLine();
        }

        // ============================================================
        // PASO 1: PENDIENTE → VENCIDO
        // Cuando la fecha de vencimiento ya pasó
        // ============================================================

        $this->info('📋 Buscando pagos PENDIENTES cuya fecha de vencimiento ya pasó...');

        $pendingToOverdue = Payment::where('estado', 'PENDIENTE')
            ->where('fecha_vencimiento', '<', $today)
            ->get();

        if ($pendingToOverdue->isEmpty()) {
            $this->line('   ✓ No hay pagos pendientes vencidos');
        } else {
            $this->warn("   ⚠ Se encontraron {$pendingToOverdue->count()} pagos pendientes vencidos:");

            foreach ($pendingToOverdue as $payment) {
                $diasVencidos = $today->diffInDays($payment->fecha_vencimiento);
                $this->line(sprintf(
                    '     • Pago #%d - Contrato: %s - Vencido hace %d días',
                    $payment->getKey(),
                    $payment->contract->codigo ?? 'N/A',
                    $diasVencidos
                ));
            }

            if (! $dryRun) {
                $updated = DB::table('pagos')
                    ->where('estado', 'PENDIENTE')
                    ->where('fecha_vencimiento', '<', $today)
                    ->update(['estado' => 'VENCIDO']);

                $this->info("   ✅ {$updated} pagos actualizados a VENCIDO");
            } else {
                $this->comment('   🔸 [DRY-RUN] Se actualizarían a VENCIDO');
            }
        }

        $this->newLine();

        // ============================================================
        // PASO 2: VENCIDO → IMPAGO
        // Cuando han pasado 7 días desde el vencimiento
        // ============================================================

        $this->info('📋 Buscando pagos VENCIDOS que superen los 7 días de tolerancia...');

        $toleranceDays = 7;
        $cutoffDate = $today->copy()->subDays($toleranceDays);

        $overdueToCancel = Payment::where('estado', 'VENCIDO')
            ->where('fecha_vencimiento', '<', $cutoffDate)
            ->with('contract')
            ->get();

        if ($overdueToCancel->isEmpty()) {
            $this->line('   ✓ No hay pagos vencidos que superen el período de tolerancia');
        } else {
            $this->warn("   ⚠ Se encontraron {$overdueToCancel->count()} pagos que superan los {$toleranceDays} días:");

            foreach ($overdueToCancel as $payment) {
                $diasVencidos = $today->diffInDays($payment->fecha_vencimiento);
                $this->line(sprintf(
                    '     • Pago #%d - Contrato: %s - Vencido hace %d días - Será IMPAGO',
                    $payment->getKey(),
                    $payment->contract->codigo ?? 'N/A',
                    $diasVencidos
                ));
            }

            if (! $dryRun) {
                $cancelled = DB::table('pagos')
                    ->where('estado', 'VENCIDO')
                    ->where('fecha_vencimiento', '<', $cutoffDate)
                    ->update([
                        'estado' => 'IMPAGO',
                        'observaciones' => DB::raw("CONCAT(COALESCE(observaciones, ''), '\nMarcado como IMPAGO automáticamente por superar 7 días de vencimiento.')"),
                    ]);

                $this->info("   ✅ {$cancelled} pagos actualizados a IMPAGO");

                // Nota: La lógica de cancelación de contratos se maneja en otro command
                // para mantener la separación de responsabilidades
            } else {
                $this->comment('   🔸 [DRY-RUN] Se actualizarían a IMPAGO');
            }
        }

        $this->newLine();

        // ============================================================
        // RESUMEN
        // ============================================================

        $this->info('📊 RESUMEN:');
        $this->line(sprintf('   • Pagos PENDIENTE → VENCIDO: %d', $pendingToOverdue->count()));
        $this->line(sprintf('   • Pagos VENCIDO → IMPAGO: %d', $overdueToCancel->count()));
        $this->line(sprintf('   • Total procesados: %d', $pendingToOverdue->count() + $overdueToCancel->count()));

        if ($dryRun) {
            $this->newLine();
            $this->comment('🔸 Ejecución en modo DRY-RUN: no se realizaron cambios');
            $this->comment('   Ejecuta sin --dry-run para aplicar los cambios');
        }

        return self::SUCCESS;
    }
}
