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
        Gate::define('manage-users', fn (User $user): bool => $user->isManager());
        Gate::define('manage-employees', fn (User $user): bool => $user->isManager());
        Gate::define('edit-employee', fn (User $user, Employee $employee): bool => $user->isManager()
            && ($user->isAdministrator() || ! $employee->accounts()->whereHas('role', fn ($q) => $q->where('nombre', Role::ADMIN))->exists()));
        Gate::define('manage-plans', fn (User $user): bool => $user->isManager());
        Gate::define('view-operations', fn (User $user): bool => $user->isManager());
        Gate::define('view-office', fn (User $user): bool => $user->isManager() || $user->isOffice());
        Gate::define('view-technician', fn (User $user): bool => $user->isManager() || $user->isTechnician());
        Gate::define('edit-user', fn (User $user, User $account): bool => $user->isManager()
            && ($account->role?->nombre !== Role::ADMIN || $user->isAdministrator()));
        Gate::define('view-access-history', fn (User $user): bool => $user->isManager());
        Gate::define('register-clients', fn (User $user): bool => $user->isManager() || $user->isOffice());

        Auth::provider('repository', fn ($app) => $app->make(RepositoryUserProvider::class));
    }
}
