<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(string $email, string $password, bool $remember, string $ip): void
    {
        $key = 'login:'.hash('sha256', $email.'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Vuelve a intentarlo en {$seconds} segundos.",
            ]);
        }

        if (! Auth::guard('web')->attempt(compact('email', 'password'), $remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($key);
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}
