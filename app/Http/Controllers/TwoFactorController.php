<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $factor) {}

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('pending_access');
        if (! is_array($pending) || ($pending['until'] ?? 0) <= now()->timestamp) {
            $this->factor->cancel(is_array($pending) ? $pending : null);
            $request->session()->forget('pending_access');

            return null;
        }

        return $pending;
    }

    public function create(Request $request)
    {
        if (! $pending = $this->pending($request)) {
            return redirect()->route('login')->withErrors(['email' => 'Inicia sesión para solicitar un código nuevo.']);
        }

        return view('auth.two-factor');
    }

    public function store(Request $request)
    {
        if (! $pending = $this->pending($request)) {
            return redirect()->route('login')->withErrors(['email' => 'La solicitud venció. Inicia sesión nuevamente.']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']], [
            'code.required' => 'Ingresa el código recibido por correo.',
            'code.regex' => 'El código debe contener 6 dígitos.',
            'code.string' => 'El código debe contener 6 dígitos.',
        ]);
        $result = $this->factor->verify($pending, $data['code'], $request->ip() ?? '', $request->userAgent() ?? '');
        $request->session()->forget('pending_access');
        Auth::guard('web')->login($result['user']);
        $request->session()->regenerate();
        $request->session()->put('login_session_id', $result['session']);

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request)
    {
        if (! $pending = $this->pending($request)) {
            return redirect()->route('login')->withErrors(['email' => 'Inicia sesión nuevamente para solicitar un código.']);
        }
        $user = User::find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('pending_access');

            return redirect()->route('login');
        }
        try {
            $request->session()->put('pending_access', $this->factor->issue($user, $pending));
        } catch (ValidationException $exception) {
            $latest = DB::table('codigos_acceso_2fa')->where('usuario_id', $user->getKey())->max('codigo_2fa_id');
            if ((int) $latest !== (int) $pending['id']) {
                $request->session()->forget('pending_access');

                return redirect()->route('login')->withErrors($exception->errors());
            }
            throw $exception;
        }

        return redirect()->route('two-factor.create')->with('status', 'Enviamos un código nuevo a tu correo. El anterior dejó de ser válido.');
    }

    public function cancel(Request $request)
    {
        $this->factor->cancel($this->pending($request));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
