<?php

namespace App\Services;

use App\Mail\AccessCode;
use App\Models\User;
use App\Repositories\AccessRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TwoFactorService
{
    public function __construct(private AccessRepository $access) {}

    public function fingerprint(User $user): string
    {
        return hash('sha256', implode('|', [$user->password_hash, $user->correo, $user->rol_id, $user->empleado_id]));
    }

    public function issue(User $user, ?array $pending = null): array
    {
        if (! app()->environment('testing') && ! in_array(config('mail.default'), ['smtp', 'brevo'], true)) {
            throw ValidationException::withMessages(['email' => 'El envío de códigos requiere configurar SMTP o la API de Brevo.']);
        }
        $issued = DB::transaction(function () use ($user, $pending): array {
            $user = User::with(['role', 'employee'])->lockForUpdate()->findOrFail($user->getKey());
            if (! $user->canAccess() || ($pending && ! $this->matches($pending, $user))) {
                throw ValidationException::withMessages(['code' => 'La solicitud ya no está disponible. Inicia sesión nuevamente.']);
            }
            $codes = DB::table('codigos_acceso_2fa')->where('usuario_id', $user->getKey());
            $last = (clone $codes)->orderByDesc('codigo_2fa_id')->first();
            if ($pending && (! $last || (int) $last->codigo_2fa_id !== (int) $pending['id'])) {
                throw ValidationException::withMessages(['code' => 'La solicitud fue reemplazada. Inicia sesión nuevamente.']);
            }
            if ($last && now()->diffInSeconds(Carbon::parse($last->solicitado_en), true) < config('access.resend_seconds')) {
                throw ValidationException::withMessages(['code' => 'Espera 60 segundos antes de solicitar otro código.']);
            }
            if ((clone $codes)->where('solicitado_en', '>', now()->subMinutes(15))->count() >= config('access.emails_per_window')) {
                throw ValidationException::withMessages(['code' => 'Alcanzaste el límite de envíos. Intenta nuevamente en 15 minutos.']);
            }
            do {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while ($last && Hash::check($code, $last->codigo_hash));
            (clone $codes)->where('estado', 'VIGENTE')->update(['estado' => 'INVALIDADO', 'invalidado_en' => now()]);
            $id = DB::table('codigos_acceso_2fa')->insertGetId([
                'usuario_id' => $user->getKey(), 'codigo_hash' => Hash::make($code),
                'solicitado_en' => now(), 'vence_en' => now()->addMinutes(config('access.code_minutes')),
                'intentos' => 0, 'estado' => 'VIGENTE',
            ]);

            return ['id' => $id, 'user' => $user, 'code' => $code];
        });
        try {
            Mail::to($issued['user']->correo)->send(new AccessCode($issued['code'], config('access.code_minutes')));
        } catch (\Throwable $exception) {
            DB::table('codigos_acceso_2fa')->where('codigo_2fa_id', $issued['id'])
                ->where('estado', 'VIGENTE')->update(['estado' => 'INVALIDADO', 'invalidado_en' => now()]);
            // Never log the message payload, SMTP credentials or temporary code.
            throw ValidationException::withMessages(['code' => 'No se pudo enviar el correo. Revisa la configuración o intenta nuevamente más tarde.']);
        }

        return [
            'id' => $issued['id'], 'user_id' => $issued['user']->getKey(),
            'fingerprint' => $this->fingerprint($issued['user']),
            'until' => $pending['until'] ?? now()->addMinutes(config('access.challenge_minutes'))->timestamp,
        ];
    }

    public function matches(array $pending, User $user): bool
    {
        return ($pending['until'] ?? 0) > now()->timestamp
            && (int) ($pending['user_id'] ?? 0) === $user->getKey()
            && hash_equals($this->fingerprint($user), $pending['fingerprint'] ?? '');
    }

    public function verify(array $pending, string $code, string $ip, string $agent): array
    {
        $result = DB::transaction(function () use ($pending, $code, $ip, $agent): array {
            $user = User::with(['role', 'employee'])->lockForUpdate()->find($pending['user_id'] ?? 0);
            if (! $user || ! $user->canAccess() || ! $this->matches($pending, $user)) {
                return ['error' => 'La solicitud ya no está disponible. Inicia sesión nuevamente.'];
            }
            $query = DB::table('codigos_acceso_2fa')->where('codigo_2fa_id', $pending['id'] ?? 0)->where('usuario_id', $user->getKey());
            $record = (clone $query)->lockForUpdate()->first();
            if (! $record || ! in_array($record->estado, ['VIGENTE', 'VENCIDO'], true)) {
                return ['error' => 'El código fue usado o invalidado. Solicita uno nuevo.'];
            }
            if ($record->estado === 'VENCIDO' || Carbon::parse($record->vence_en)->lte(now())) {
                $query->update(['estado' => 'VENCIDO']);
                $this->access->record($user, $user->nombre_usuario, '2FA_VENCIDO', $ip, $agent);

                return ['error' => 'El código ha vencido. Solicita uno nuevo.'];
            }
            if (! Hash::check($code, $record->codigo_hash)) {
                $attempts = $record->intentos + 1;
                $changes = ['intentos' => $attempts];
                if ($attempts >= config('access.code_attempts')) {
                    $changes += ['estado' => 'INVALIDADO', 'invalidado_en' => now()];
                }
                $query->update($changes);
                $this->access->record($user, $user->nombre_usuario, '2FA_INCORRECTO', $ip, $agent);

                return ['error' => $attempts >= config('access.code_attempts')
                    ? 'Alcanzaste el límite de intentos. Solicita otro código.' : 'El código ingresado no es correcto.'];
            }
            $query->update(['estado' => 'USADO', 'usado_en' => now()]);
            $session = $this->access->begin($user, $ip, $agent);
            $this->access->record($user, $user->nombre_usuario, 'EXITOSO', $ip, $agent);

            return ['user' => $user, 'session' => $session];
        });
        if (isset($result['error'])) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        return $result;
    }

    public function cancel(?array $pending): void
    {
        if ($pending) {
            DB::table('codigos_acceso_2fa')->where('codigo_2fa_id', $pending['id'] ?? 0)
                ->where('usuario_id', $pending['user_id'] ?? 0)->where('estado', 'VIGENTE')
                ->update(['estado' => 'INVALIDADO', 'invalidado_en' => now()]);
        }
    }
}
