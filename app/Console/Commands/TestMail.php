<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class TestMail extends Command
{
    protected $signature = 'app:mail-test {recipient : Correo donde recibir la prueba}';

    protected $description = 'Enviar un correo de prueba mediante Brevo o SMTP sin activar el segundo factor';

    public function handle(): int
    {
        $recipient = $this->argument('recipient');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Introduce un correo válido, sin barras antes del @.');

            return self::FAILURE;
        }
        if (! in_array(config('mail.default'), ['brevo', 'smtp'], true)) {
            $this->error('Configura MAIL_MAILER=brevo o MAIL_MAILER=smtp para enviar un correo real.');

            return self::FAILURE;
        }
        try {
            Mail::raw('Prueba de envío desde Lunnys Perú. Si recibiste este mensaje, el envío de correo funciona.', function ($message) use ($recipient) {
                $message->to($recipient)->subject('Prueba de correo - Lunnys Perú');
            });
        } catch (TransportExceptionInterface $error) {
            $this->error(config('mail.default') === 'brevo' ? $error->getMessage() : 'No se pudo enviar por SMTP. Revisa la configuración y la conexión.');

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('No se pudo enviar el correo. Revisa el remitente y la configuración del servicio.');

            return self::FAILURE;
        }
        $this->info('El proveedor aceptó el correo. Comprueba la bandeja de entrada y spam antes de activar el segundo factor.');

        return self::SUCCESS;
    }
}
