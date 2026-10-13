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
            'manage-plans' => ['plans.create','plans.update','plans.status'], 'manage-zones' => ['zones.create','zones.update','zones.status'], 'view-zones' => ['zones.view'],
            'view-operations' => ['orders.view', 'equipments.view', 'materials.view', 'chatbot.view', 'indicators.view', 'bi.view'],
            // DESHABILITADO TEMPORALMENTE - sales.view removido de view-office
            'view-office' => ['contracts.view', 'plans.view', 'payments.view', 'reports.view'],
            'view-technician' => ['technician.view'], 'view-access-history' => ['access.view'],
            'register-clients' => ['clients.view'], 'manage-payments' => ['payments.view'],
        ];
        foreach ($aliases as $ability => $permissions) {
            Gate::define($ability, fn (User $user): bool => collect($permissions)->contains(fn ($p) => $user->hasPermission($p)));
        }
        Gate::define('edit-user', fn (User $user, User $account): bool => ($user->hasPermission('users.update') || $user->hasPermission('users.status'))
            && (! $account->hasRole(Role::ADMIN, false) || $user->isAdministrator()));
        Gate::define('edit-employee', fn (User $user, Employee $employee): bool => $user->isAdministrator()
            && ($user->isAdministrator() || ! $employee->accounts()->whereHas('roles', fn ($q) => $q->where('nombre', Role::ADMIN))->exists()));

        foreach (['clients','plans','contracts','payments','zones','employees','users','equipments','materials','technician'] as $module) {
            Gate::define($module.'.manage', fn (User $user) => collect(array_keys(\App\Support\AccessCatalog::permissions()))->contains(fn ($p) => str_starts_with($p, $module.'.') && !in_array(explode('.', $p)[1], ['view','assigned','availability','related','panel','supervise']) && $user->hasPermission($p)));
        }
        \Illuminate\Support\Facades\Blade::if('routecan', fn (string $name) => auth()->user()?->hasPermission(\App\Support\AccessCatalog::forRoute($name) ?? '') ?? false);

        Auth::provider('repository', fn ($app) => $app->make(RepositoryUserProvider::class));
    }
}
