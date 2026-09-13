<?php

namespace App\Services;

use App\Repositories\AccessRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private UserRepository $users, private AccessRepository $access, private TwoFactorService $factor) {}

    public function login(string $login, string $password, string $ip, string $agent = ''): array
    {
        $key = 'login:'.hash('sha256', $login.'|'.$ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Vuelve a intentarlo en '.RateLimiter::availableIn($key).' segundos.']);
        }
        $user = $this->users->findByLogin($login);
        $valid = Hash::check($password, $user?->password_hash ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $valid || ! $user->canAccess()) {
            RateLimiter::hit($key, 60);
            $result = $user?->estado === 'BLOQUEADO' ? 'BLOQUEADO' : ($user && ! $user->canAccess() ? 'CUENTA_INACTIVA' : 'CREDENCIALES_INCORRECTAS');
            $this->access->record($user, $login, $result, $ip, $agent);
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas o la cuenta no está disponible.']);
        }
        $pending = $this->factor->issue($user);
        RateLimiter::clear($key);

        return $pending;
    }

    public function logout(?string $session): void
    {
        $this->access->close($session);
        Auth::guard('web')->logout();
    }
}
