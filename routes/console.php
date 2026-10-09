<?php

use App\Services\EmployeeService;
use App\Services\ContractService;
use App\Services\InstallationDriveService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('employees:expire', function () {
    $count = app(EmployeeService::class)->expire();
    $this->info("Empleados procesados por fecha de cese: {$count}");
})->purpose('Inhabilitar empleados con vínculo vencido y sus cuentas');

Schedule::command('employees:expire')->everyMinute()->withoutOverlapping();

Artisan::command('contracts:finalize', function () {
    $count = app(ContractService::class)->finalizeExpired();
    $this->info("Contratos finalizados por vencimiento: {$count}");
})->purpose('Finalizar contratos activos al terminar su vigencia');

Artisan::command('installations:drive-folder', function () {
    $id = app(InstallationDriveService::class)->createFolder();
    $this->info('ID de carpeta para INSTALLATION_DRIVE_FOLDER_ID: '.$id);
})->purpose('Crear en Drive la carpeta privada de evidencias de instalación');

Artisan::command('installations:organize-drive {pedido? : ID del pedido; omitir para revisar todas las evidencias confirmadas}', function () {
    $pedido = $this->argument('pedido');
    if ($pedido !== null && (! ctype_digit((string) $pedido) || (int) $pedido < 1)) {
        $this->error('Indica un ID de pedido válido.');
        return 1;
    }
    try {
        $result = app(InstallationDriveService::class)->organizeConfirmed($pedido === null ? null : (int) $pedido);
    } catch (\Throwable $error) {
        $this->error('No se pudo acceder a Drive: '.$error->getMessage());
        return 1;
    }
    $this->info("Archivos trasladados: {$result['moved']}. Ya organizados: {$result['already_organized']}. Fallidos: {$result['failed']}.");
    return $result['failed'] === 0 ? 0 : 1;
})->purpose('Organizar las evidencias existentes de Drive por pedido y tipo');

Schedule::command('contracts:finalize')->everyMinute()->withoutOverlapping();

// ============================================================================
// AUTOMATIZACIÓN DE PAGOS Y CONTRATOS (RF-PAG-10, RF-PAG-11, RF-PAG-12)
// ============================================================================

// Actualizar estados de pagos: PENDIENTE → VENCIDO → IMPAGO
Schedule::command('payments:update-overdue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Cancelar contratos por falta de pago (cuando tienen pagos ANULADOS)
Schedule::command('contracts:cancel-for-non-payment')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Enviar recordatorios de pago por correo (3 tipos: previo, vencido, cancelación)
Schedule::command('payments:send-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// ============================================================================

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
