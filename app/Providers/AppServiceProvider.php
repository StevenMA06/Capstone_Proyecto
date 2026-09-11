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

        Auth::provider('repository', fn ($app) => $app->make(RepositoryUserProvider::class));
    }
}
