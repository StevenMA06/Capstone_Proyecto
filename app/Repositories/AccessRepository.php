<?php

namespace App\Repositories;

use App\Models\User;
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
}
