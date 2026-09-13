<?php

namespace App\Providers;

use App\Auth\RepositoryUserProvider;
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
        Gate::define('view-access-history', fn (User $user): bool => $user->isManager());
        Gate::define('register-clients', fn (User $user): bool => $user->canAccess()
            && in_array(mb_strtolower($user->role->nombre), ['gerencia', 'personal autorizado'], true));

        Auth::provider('repository', fn ($app) => $app->make(RepositoryUserProvider::class));
    }
}
