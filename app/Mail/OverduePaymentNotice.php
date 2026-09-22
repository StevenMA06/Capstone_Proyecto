<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Mail\Mailable;

class OverduePaymentNotice extends Mailable
{
    public function __construct(public Payment $payment) {}

    public function build(): static
    {
        $this->payment->load(['contract.client', 'contract.plan']);

        $cliente = $this->payment->contract->client;
        $contrato = $this->payment->contract;
        $plan = $this->payment->contract->plan;
        
        $diasVencidos = $this->payment->fecha_vencimiento->diffInDays(now(), false);

        return $this
            ->subject('Importante: Pago vencido - Lunnys Perú')
            ->view('emails.overdue-payment-notice', [
                'payment' => $this->payment,
                'cliente' => $cliente,
                'contrato' => $contrato,
                'plan' => $plan,
                'diasVencidos' => $diasVencidos,
            ]);
    }
}
