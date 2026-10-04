<?php

namespace App\Providers;

use App\Auth\RepositoryUserProvider;
use App\Mail\Transport\BrevoTransport;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Mail::extend('brevo', fn () => new BrevoTransport((string) config('services.brevo.key')));
        foreach (array_keys(\App\Support\AccessCatalog::permissions()) as $code) {
            Gate::define($code, fn (User $user): bool => $user->hasPermission($code));
        }
        $aliases = [
            'manage-users' => ['users.view'], 'manage-employees' => ['employees.view'],
            'manage-plans' => ['plans.manage'], 'manage-zones' => ['zones.manage'], 'view-zones' => ['zones.view'],
            'view-operations' => ['installations.view', 'equipments.view', 'materials.view', 'chatbot.view', 'indicators.view', 'bi.view'],
            'view-office' => ['sales.view', 'contracts.view', 'plans.view', 'payments.view', 'reports.view'],
            'view-technician' => ['technician.view'], 'view-access-history' => ['access.view'],
            'register-clients' => ['clients.view'], 'manage-payments' => ['payments.view'],
        ];
        foreach ($aliases as $ability => $permissions) {
            Gate::define($ability, fn (User $user): bool => collect($permissions)->contains(fn ($p) => $user->hasPermission($p)));
        }
        Gate::define('edit-user', fn (User $user, User $account): bool => $user->hasPermission('users.manage')
            && (! $account->hasRole(Role::ADMIN, false) || $user->isAdministrator()));
        Gate::define('edit-employee', fn (User $user, Employee $employee): bool => $user->hasPermission('employees.manage')
            && ($user->isAdministrator() || ! $employee->accounts()->whereHas('roles', fn ($q) => $q->where('nombre', Role::ADMIN))->exists()));

        Auth::provider('repository', fn ($app) => $app->make(RepositoryUserProvider::class));
    }
}
