<?php

namespace App\Providers;

use App\Auth\RepositoryUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Auth::provider('repository', fn ($app) => $app->make(RepositoryUserProvider::class));
    }
}
