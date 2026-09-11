<?php

namespace App\Http\Middleware;

use App\Repositories\AccessRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function __construct(private AccessRepository $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $id = $request->session()->get('login_session_id');
            if (! $user->canAccess() || ! is_string($id) || ! $this->access->touch($id, $user)) {
                $this->access->close(is_string($id) ? $id : null);
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'La sesión finalizó o la cuenta no está disponible. Inicia sesión nuevamente.']);
            }
        }

        return $next($request);
    }
}
