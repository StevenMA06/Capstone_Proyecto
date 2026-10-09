<?php

namespace App\Console\Commands;

use App\Services\InstallationOrderService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class TestTechnicianMail extends Command
{
    protected $signature = 'installations:mail-test {pedido : Número de la ficha de instalación} {--preview : Mostrar destinatario y enlace sin enviar correo}';

    protected $description = 'Enviar de nuevo el enlace al técnico asignado sin modificar el pedido';

    public function handle(InstallationOrderService $orders): int
    {
        $id = (string) $this->argument('pedido');
        if (! ctype_digit($id) || (int) $id < 1) {
            $this->error('Indica el número de una ficha existente, por ejemplo: 1.');

            return self::FAILURE;
        }
        $preview = (bool) $this->option('preview');
        if (! $preview && ! in_array(config('mail.default'), ['brevo', 'smtp'], true)) {
            $this->error('Configura MAIL_MAILER=brevo o MAIL_MAILER=smtp para enviar un correo real.');

            return self::FAILURE;
        }

        try {
            $result = $orders->testTechnicianMail((int) $id, ! $preview);
        } catch (ValidationException $error) {
            $this->error($error->validator->errors()->first());

            return self::FAILURE;
        } catch (QueryException) {
            $this->error('No se pudo consultar la ficha. Revisa la conexión con la base de datos y las migraciones.');

            return self::FAILURE;
        } catch (TransportExceptionInterface $error) {
            $this->error(config('mail.default') === 'brevo' ? $error->getMessage() : 'No se pudo enviar por SMTP. Revisa la configuración y la conexión.');

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('No se pudo enviar el correo. Revisa el remitente y la configuración del servicio.');

            return self::FAILURE;
        }

        $this->info(($preview ? 'Vista previa' : 'El proveedor aceptó el correo').' de '.$result['codigo'].': '.$result['correo']);
        $this->line('Inicio de sesión: '.$result['login_url']);
        $this->line('Ficha de instalación: '.$result['url']);
        if (! $preview) $this->line('Comprueba la bandeja de entrada y spam del técnico.');

        return self::SUCCESS;
    }
}
