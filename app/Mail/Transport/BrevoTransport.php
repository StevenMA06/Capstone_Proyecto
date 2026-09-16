<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    public function __construct(#[\SensitiveParameter] private readonly string $apiKey)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'brevo+https';
    }

    protected function doSend(SentMessage $message): void
    {
        if (trim($this->apiKey) === '') {
            throw new TransportException('Configura BREVO_API_KEY en el archivo .env.');
        }
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || $email->getAttachments()) {
            throw new TransportException('El envío Brevo admite correos de texto o HTML sin adjuntos.');
        }
        $address = static fn (Address $a): array => array_filter(['email' => $a->getAddress(), 'name' => $a->getName()], fn ($v) => $v !== '');
        $payload = ['sender' => $address($email->getFrom()[0]), 'subject' => $email->getSubject()];
        foreach (['to' => $email->getTo(), 'cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $recipients) {
            if ($recipients) {
                $payload[$field] = array_map($address, $recipients);
            }
        }
        if ($email->getReplyTo()) {
            $payload['replyTo'] = $address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }
        try {
            // No redirects or retries: never forward the key or duplicate a temporary code.
            $response = Http::acceptJson()->asJson()->withHeaders(['api-key' => $this->apiKey])
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (\Throwable) {
            // Do not include the HTTP exception, request, credentials or code in logs.
            throw new TransportException('No se pudo conectar con Brevo por HTTPS. Revisa la conexión y los certificados de PHP.');
        }
        if ($response->status() !== 201 || ! is_string($response->json('messageId')) || $response->json('messageId') === '') {
            $hint = match ($response->status()) {
                401 => 'Verifica la clave API de Brevo.',
                403 => 'Revisa los permisos, las IP autorizadas para API y el estado de la cuenta Brevo.',
                429 => 'Se alcanzó un límite de Brevo. Intenta más tarde.',
                default => 'Revisa el remitente verificado, los límites y los registros transaccionales de Brevo.',
            };
            throw new TransportException('Brevo no confirmó el envío (HTTP '.$response->status().'). '.$hint);
        }
        $message->setMessageId($response->json('messageId'));
    }
}
