<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $remaining = DB::transaction(function () use ($request): ?int {
            $session = DB::table('sesiones_usuario')->where('sesion_id', $request->session()->get('login_session_id'))
                ->where('usuario_id', $request->user()->getKey())->lockForUpdate()->first();
            if ($session && $session->estado === 'ACTIVA' && $request->user()->canAccess()
                && Carbon::parse($session->expira_en)->gt(now())) {
                return (int) ceil(now()->diffInSeconds(Carbon::parse($session->expira_en)));
            }
            if ($session && $session->estado === 'ACTIVA') {
                DB::table('sesiones_usuario')->where('sesion_id', $session->sesion_id)->where('estado', 'ACTIVA')
                    ->update(['estado' => Carbon::parse($session->expira_en)->lte(now()) ? 'EXPIRADA' : 'CERRADA', 'cerrada_en' => now()]);
            }

            return null;
        });
        if ($remaining !== null) {
            return response()->json(['remaining' => $remaining])->header('Cache-Control', 'no-store');
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['expired' => true], 401)->header('Cache-Control', 'no-store');
    }
}
