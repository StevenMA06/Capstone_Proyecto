<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $request->session()->forget('pending_access');
        $pending = $this->auth->login($credentials['email'], $credentials['password'], $request->ip() ?? '127.0.0.1', $request->userAgent() ?? '');
        $request->session()->regenerate();
        if (isset($pending['session_id'])) {
            $request->session()->put('login_session_id', $pending['session_id']);

            $request->session()->forget('url.intended');

            return redirect()->route('dashboard');
        }
        $request->session()->put('pending_access', $pending);

        return redirect()->route('two-factor.create');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout($request->session()->get('login_session_id'));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Cerraste sesión correctamente.');
    }
}
