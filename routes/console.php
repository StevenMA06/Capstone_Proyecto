<?php

use App\Services\EmployeeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('employees:expire', function () {
    $count = app(EmployeeService::class)->expire();
    $this->info("Empleados procesados por fecha de cese: {$count}");
})->purpose('Inhabilitar empleados con vínculo vencido y sus cuentas');

Schedule::command('employees:expire')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
