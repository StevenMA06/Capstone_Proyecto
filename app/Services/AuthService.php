<?php

namespace App\Services;

use App\Repositories\AccessRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private UserRepository $users, private AccessRepository $access) {}

    public function login(string $login, string $password, string $ip, string $agent = ''): string
    {
        $key = 'login:'.hash('sha256', $login.'|'.$ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Demasiados intentos. Vuelve a intentarlo en {$seconds} segundos."]);
        }

        if (! Auth::guard('web')->attemptWhen(['login' => $login, 'password' => $password], fn ($user) => $user->canAccess())) {
            RateLimiter::hit($key, 60);
            $user = $this->users->findByLogin($login);
            $result = $user?->estado === 'BLOQUEADO' ? 'BLOQUEADO' : ($user && ! $user->canAccess() ? 'CUENTA_INACTIVA' : 'CREDENCIALES_INCORRECTAS');
            $this->access->record($user, $login, $result, $ip, $agent);
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas o la cuenta no está disponible.']);
        }

        try {
            $user = Auth::guard('web')->user();
            $session = $this->access->begin($user, $ip, $agent);
            $this->access->record($user, $login, 'EXITOSO', $ip, $agent);
        } catch (\Throwable $exception) {
            Auth::guard('web')->logout();
            throw $exception;
        }
        RateLimiter::clear($key);

        return $session;
    }

    public function logout(?string $session): void
    {
        $this->access->close($session);
        Auth::guard('web')->logout();
    }
}
