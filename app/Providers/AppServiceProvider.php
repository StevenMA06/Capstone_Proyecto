<?php

namespace App\Providers;

use App\Auth\RepositoryUserProvider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->isManager());
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
