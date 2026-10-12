<?php

namespace App\Console\Commands;

use App\Mail\ContractCancellationNotice;
use App\Mail\OverduePaymentNotice;
use App\Mail\UpcomingPaymentReminder;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:send-reminders
                            {--dry-run : Execute without sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía recordatorios automáticos de pagos: próximos a vencer (7 días antes), vencidos (4 días después) y cancelaciones';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today = now()->startOfDay();

        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: no se enviarán correos reales');
            $this->newLine();
        }

        $totalSent = 0;

        // ============================================================
        // PASO 1: Recordatorios previos (7 días antes)
        // ============================================================

        $this->info('📧 Buscando pagos próximos a vencer (7 días antes)...');

        $upcomingPayments = Payment::where('estado', 'PENDIENTE')
            ->whereNull('recordatorio_previo_enviado_en')
            ->whereDate('fecha_vencimiento', '>=', $today)
            ->whereDate('fecha_vencimiento', '<=', $today->copy()->addDays(7))
            ->with(['contract.client', 'contract.plan'])
            ->get();

        if ($upcomingPayments->isEmpty()) {
            $this->line('   ✓ No hay pagos próximos a vencer sin recordatorio');
        } else {
            $this->warn("   ⚠ Se encontraron {$upcomingPayments->count()} pagos próximos a vencer:");

            foreach ($upcomingPayments as $payment) {
                $cliente = $payment->contract->client;
                $diasRestantes = $today->diffInDays($payment->fecha_vencimiento, false);

                if (! $cliente || ! $cliente->correo) {
                    $this->line(sprintf(
                        '     ⊗ Pago #%d - Sin correo del cliente',
                        $payment->getKey()
                    ));
                    continue;
                }

                $this->line(sprintf(
                    '     • Pago #%d - Cliente: %s - Vence en %d días - Email: %s',
                    $payment->getKey(),
                    $cliente->nombres_razon_social,
                    $diasRestantes,
                    $cliente->correo
                ));

                if (! $dryRun) {
                    try {
                        Mail::to($cliente->correo)->send(new UpcomingPaymentReminder($payment));
                        $payment->markUpcomingReminderSent();
                        $this->info('       ✅ Correo enviado exitosamente');
                        $totalSent++;
                    } catch (\Exception $e) {
                        $this->error('       ✗ Error al enviar: '.$e->getMessage());
                    }
                } else {
                    $this->comment('       🔸 [DRY-RUN] Se enviaría recordatorio previo');
                }
            }
        }

        $this->newLine();

        // ============================================================
        // PASO 2: Recordatorios vencidos (4 días después - CAMBIADO de 5 a 4)
        // ============================================================

        $this->info('📧 Buscando pagos vencidos (4 días después del vencimiento)...');

        $cutoffDate = $today->copy()->subDays(4);  // CAMBIADO: Antes era 5 días

        $overduePayments = Payment::where('estado', 'VENCIDO')
            ->whereNull('recordatorio_vencido_enviado_en')
            ->whereDate('fecha_vencimiento', '<=', $cutoffDate)
            ->with(['contract.client', 'contract.plan'])
            ->get();

        if ($overduePayments->isEmpty()) {
            $this->line('   ✓ No hay pagos vencidos que requieran recordatorio');
        } else {
            $this->warn("   ⚠ Se encontraron {$overduePayments->count()} pagos vencidos:");

            foreach ($overduePayments as $payment) {
                $cliente = $payment->contract->client;
                $diasVencidos = $payment->fecha_vencimiento->diffInDays($today, false);

                if (! $cliente || ! $cliente->correo) {
                    $this->line(sprintf(
                        '     ⊗ Pago #%d - Sin correo del cliente',
                        $payment->getKey()
                    ));
                    continue;
                }

                $this->line(sprintf(
                    '     • Pago #%d - Cliente: %s - Vencido hace %d días - Email: %s',
                    $payment->getKey(),
                    $cliente->nombres_razon_social,
                    $diasVencidos,
                    $cliente->correo
                ));

                if (! $dryRun) {
                    try {
                        Mail::to($cliente->correo)->send(new OverduePaymentNotice($payment));
                        $payment->markOverdueReminderSent();
                        $this->info('       ✅ Correo enviado exitosamente');
                        $totalSent++;
                    } catch (\Exception $e) {
                        $this->error('       ✗ Error al enviar: '.$e->getMessage());
                    }
                } else {
                    $this->comment('       🔸 [DRY-RUN] Se enviaría aviso de vencido');
                }
            }
        }

        $this->newLine();

        // ============================================================
        // PASO 3: Notificaciones de cancelación
        // ============================================================

        $this->info('📧 Buscando contratos cancelados que requieren notificación...');

        $cancelledContracts = Contract::where('estado', 'CANCELADO')
            ->whereHas('payments', function ($query) {
                $query->where('estado', 'ANULADO')
                    ->whereNull('notificacion_anulacion_enviada_en');
            })
            ->with(['client', 'plan', 'payments' => function ($query) {
                $query->where('estado', 'ANULADO')
                    ->whereNull('notificacion_anulacion_enviada_en')
                    ->orderBy('fecha_vencimiento');
            }])
            ->get();

        if ($cancelledContracts->isEmpty()) {
            $this->line('   ✓ No hay cancelaciones que requieran notificación');
        } else {
            $this->warn("   ⚠ Se encontraron {$cancelledContracts->count()} contratos cancelados:");

            foreach ($cancelledContracts as $contract) {
                $cliente = $contract->client;
                $pagoAnulado = $contract->payments->first();

                if (! $cliente || ! $cliente->correo || ! $pagoAnulado) {
                    $this->line(sprintf(
                        '     ⊗ Contrato #%d - Sin correo o sin pago anulado',
                        $contract->getKey()
                    ));
                    continue;
                }

                $this->line(sprintf(
                    '     • Contrato #%d (%s) - Cliente: %s - Email: %s',
                    $contract->getKey(),
                    $contract->codigo,
                    $cliente->nombres_razon_social,
                    $cliente->correo
                ));

                if (! $dryRun) {
                    try {
                        Mail::to($cliente->correo)->send(
                            new ContractCancellationNotice($contract, $pagoAnulado)
                        );
                        $pagoAnulado->markCancellationNotificationSent();
                        $this->info('       ✅ Notificación de cancelación enviada');
                        $totalSent++;
                    } catch (\Exception $e) {
                        $this->error('       ✗ Error al enviar: '.$e->getMessage());
                    }
                } else {
                    $this->comment('       🔸 [DRY-RUN] Se enviaría notificación de cancelación');
                }
            }
        }

        $this->newLine();

        // ============================================================
        // RESUMEN
        // ============================================================

        $this->info('📊 RESUMEN:');
        $this->line(sprintf('   • Recordatorios previos: %d', $upcomingPayments->count()));
        $this->line(sprintf('   • Recordatorios vencidos: %d', $overduePayments->count()));
        $this->line(sprintf('   • Notificaciones cancelación: %d', $cancelledContracts->count()));
        $this->line(sprintf('   • Total correos enviados: %d', $dryRun ? 0 : $totalSent));

        if ($dryRun) {
            $this->newLine();
            $this->comment('🔸 Ejecución en modo DRY-RUN: no se enviaron correos');
            $this->comment('   Ejecuta sin --dry-run para enviar los correos reales');
        }

        return self::SUCCESS;
    }
}
