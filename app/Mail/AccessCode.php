<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AccessCode extends Mailable
{
    public function __construct(public string $code, public int $minutes) {}

    public function build(): static
    {
        return $this->subject('Tu código de acceso a Lunnys Perú')->view('emails.access-code');
    }
}
