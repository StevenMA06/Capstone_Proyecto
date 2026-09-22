<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Mail\Mailable;

class ContractCancellationNotice extends Mailable
{
    public function __construct(
        public Contract $contract,
        public Payment $payment
    ) {}

    public function build(): static
    {
        $this->contract->load(['client', 'plan']);
        $this->payment->load(['contract']);

        $cliente = $this->contract->client;
        $plan = $this->contract->plan;

        return $this
            ->subject('Cancelación de servicio por falta de pago - Lunnys Perú')
            ->view('emails.contract-cancellation-notice', [
                'contract' => $this->contract,
                'payment' => $this->payment,
                'cliente' => $cliente,
                'plan' => $plan,
            ]);
    }
}
