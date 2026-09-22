<?php

use App\Services\EmployeeService;
use App\Services\ContractService;
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

Schedule::command('contracts:finalize')->everyMinute()->withoutOverlapping();

// ============================================================================
// AUTOMATIZACIÓN DE PAGOS Y CONTRATOS (RF-PAG-10, RF-PAG-11, RF-PAG-12)
// ============================================================================

// Actualizar estados de pagos: PENDIENTE → VENCIDO → ANULADO
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
