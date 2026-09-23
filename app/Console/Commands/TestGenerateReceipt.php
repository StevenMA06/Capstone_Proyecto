<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentReceiptPdf;
use Illuminate\Console\Command;

class TestGenerateReceipt extends Command
{
    protected $signature = 'test:generate-receipt {payment_id}';
    protected $description = 'Generar comprobante de pago para pruebas';

    public function handle(PaymentReceiptPdf $receiptService): int
    {
        $paymentId = $this->argument('payment_id');
        
        $payment = Payment::with(['contract.client', 'contract.plan'])->find($paymentId);
        
        if (!$payment) {
            $this->error("❌ Pago #{$paymentId} no encontrado");
            return self::FAILURE;
        }
        
        $this->info("📄 Generando comprobante para pago: {$payment->codigo}");
        
        try {
            $path = $receiptService->generate($payment);
            $this->info("Comprobante generado: {$path}");
            $this->info("🔗 URL: http://localhost/" . $path);
            
            // Actualizar ruta en BD
            $payment->constancia_ruta = $path;
            $payment->save();
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
