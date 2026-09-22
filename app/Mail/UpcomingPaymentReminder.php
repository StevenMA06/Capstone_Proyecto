<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Mail\Mailable;

class UpcomingPaymentReminder extends Mailable
{
    public function __construct(public Payment $payment) {}

    public function build(): static
    {
        $this->payment->load(['contract.client', 'contract.plan']);

        $cliente = $this->payment->contract->client;
        $contrato = $this->payment->contract;
        $plan = $this->payment->contract->plan;
        
        $diasHastaVencimiento = now()->diffInDays($this->payment->fecha_vencimiento, false);

        return $this
            ->subject('Recordatorio: Próximo vencimiento de pago - Lunnys Perú')
            ->view('emails.upcoming-payment-reminder', [
                'payment' => $this->payment,
                'cliente' => $cliente,
                'contrato' => $contrato,
                'plan' => $plan,
                'diasHastaVencimiento' => $diasHastaVencimiento,
            ]);
    }
}
