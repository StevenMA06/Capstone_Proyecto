<?php

namespace App\Repositories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessRepository
{
    public function record(?User $user, string $login, string $result, string $ip, string $agent): void
    {
        DB::table('historial_accesos')->insert([
            'usuario_id' => $user?->getKey(), 'nombre_usuario_intentado' => mb_substr($login, 0, 60),
            'fecha_hora' => now(), 'resultado' => $result, 'ip' => $ip, 'user_agent' => mb_substr($agent, 0, 500),
        ]);
    }

    public function begin(User $user, string $ip, string $agent): string
    {
        $id = (string) Str::uuid();
        DB::table('sesiones_usuario')->insert([
            'sesion_id' => $id, 'usuario_id' => $user->getKey(), 'iniciada_en' => now(),
            'ultima_actividad_en' => now(), 'expira_en' => now()->addMinutes((int) config('session.lifetime')),
            'ip' => $ip, 'user_agent' => mb_substr($agent, 0, 500), 'estado' => 'ACTIVA',
        ]);
        $user->forceFill(['ultimo_acceso_en' => now()])->save();

        return $id;
    }

    public function touch(string $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user): bool {
            $query = DB::table('sesiones_usuario')->where('sesion_id', $id)->where('usuario_id', $user->getKey())
                ->where('estado', 'ACTIVA')->where('expira_en', '>', now());
            if (! $query->lockForUpdate()->first()) {
                DB::table('sesiones_usuario')->where('sesion_id', $id)->where('usuario_id', $user->getKey())
                    ->where('estado', 'ACTIVA')->where('expira_en', '<=', now())
                    ->update(['estado' => 'EXPIRADA', 'cerrada_en' => now()]);

                return false;
            }
            $query->update(['ultima_actividad_en' => now(), 'expira_en' => now()->addMinutes((int) config('session.lifetime'))]);

            return true;
        });
    }

    public function close(?string $id): void
    {
        if ($id) {
            DB::table('sesiones_usuario')->where('sesion_id', $id)->where('estado', 'ACTIVA')
                ->update(['estado' => 'CERRADA', 'cerrada_en' => now()]);
        }
    }

    public function history(array $filters): LengthAwarePaginator
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search'] ?? '').'%';

        return DB::table('historial_accesos as h')->leftJoin('usuarios as u', 'u.usuario_id', '=', 'h.usuario_id')
            ->select('h.*', 'u.nombre_usuario')
            ->when(! empty($filters['search']), fn ($query) => $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("u.nombre_usuario LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("h.nombre_usuario_intentado LIKE ? ESCAPE '!'", [$pattern]);
            }))
            ->when(! empty($filters['date']), fn ($query) => $query->where('h.fecha_hora', '>=', $filters['date'].' 00:00:00')
                ->where('h.fecha_hora', '<', Carbon::parse($filters['date'])->addDay()->format('Y-m-d').' 00:00:00'))
            ->when(($filters['result'] ?? '') === 'EXITOSO', fn ($query) => $query->where('h.resultado', 'EXITOSO'))
            ->when(($filters['result'] ?? '') === 'RECHAZADO', fn ($query) => $query->where('h.resultado', '<>', 'EXITOSO'))
            ->orderByDesc('h.fecha_hora')->orderByDesc('h.acceso_id')->paginate(20)->withQueryString();
    }
}
