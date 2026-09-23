<?php

namespace App\Console\Commands;

use App\Mail\OverduePaymentNotice;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSendOverdueEmail extends Command
{
    protected $signature = 'test:send-overdue-email {--payment= : ID del pago}';
    protected $description = 'Envía correo de prueba para pagos vencidos (sin esperar 5 días)';

    public function handle(): int
    {
        $paymentId = $this->option('payment');
        
        if ($paymentId) {
            // Enviar a un pago específico
            $payment = Payment::with(['contract.client', 'contract.plan'])->find($paymentId);
            
            if (!$payment) {
                $this->error("❌ Pago #{$paymentId} no encontrado");
                return self::FAILURE;
            }
            
            $payments = collect([$payment]);
        } else {
            // Buscar todos los pagos vencidos
            $payments = Payment::where('estado', 'VENCIDO')
                ->with(['contract.client', 'contract.plan'])
                ->get();
        }

        if ($payments->isEmpty()) {
            $this->info('✓ No hay pagos vencidos para enviar correo');
            return self::SUCCESS;
        }

        $this->info("📧 Enviando correos a {$payments->count()} pago(s) vencido(s)...");
        $this->newLine();

        $sent = 0;
        foreach ($payments as $payment) {
            $cliente = $payment->contract->client;
            
            if (!$cliente || !$cliente->correo) {
                $this->line("   ⊗ Pago #{$payment->pago_id} - Cliente sin correo");
                continue;
            }

            $this->line("   📨 Enviando a: {$cliente->correo}");
            $this->line("      Cliente: {$cliente->nombres_razon_social}");
            $this->line("      Monto: S/ {$payment->monto}");
            $this->line("      Vencido: {$payment->fecha_vencimiento->format('d/m/Y')}");

            try {
                Mail::to($cliente->correo)->send(new OverduePaymentNotice($payment));
                $this->info("      ✅ Correo enviado exitosamente");
                $sent++;
            } catch (\Exception $e) {
                $this->error("      ✗ Error: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        $this->info("📊 Total correos enviados: {$sent}");
        return self::SUCCESS;
    }
}
