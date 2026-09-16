<?php

namespace App\Repositories;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function findByLogin(string $login): ?User
    {
        return User::with(['role', 'employee'])->where(str_contains($login, '@') ? 'correo' : 'nombre_usuario', $login)->first();
    }

    public function roles(bool $activeOnly = true): Collection
    {
        return Role::query()->whereIn('nombre', Role::NAMES)->when($activeOnly, fn ($q) => $q->where('estado', 'ACTIVO'))->orderBy('nombre')->get();
    }

    public function employees(?User $account = null): Collection
    {
        return Employee::eligible()->where(function ($q) use ($account): void {
            $q->whereNotIn('empleado_id', User::whereNotNull('empleado_id')->select('empleado_id'));
            if ($account?->empleado_id) {
                $q->orWhere('empleado_id', $account->empleado_id);
            }
        })->orderBy('nombres')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function paginate(string $search, ?int $role): LengthAwarePaginator
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        return User::with(['role', 'employee'])
            ->when($search !== '', fn ($q) => $q->whereRaw("nombre_usuario LIKE ? ESCAPE '!'", [$pattern]))
            ->when($role, fn ($q) => $q->where('rol_id', $role))
            ->orderBy('nombre_usuario')->paginate(15)->withQueryString();
    }

    public function lockManagers(): Collection
    {
        return User::with(['role', 'employee'])->whereHas('role', fn ($q) => $q->whereIn('nombre', [Role::ADMIN, Role::MANAGER]))
            ->orderBy('usuario_id')->lockForUpdate()->get();
    }

    public function lock(int $id): User
    {
        return User::with(['role', 'employee'])->lockForUpdate()->findOrFail($id);
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user->refresh();
    }

    public function revokeSessions(User $user): void
    {
        DB::table('sesiones_usuario')->where('usuario_id', $user->getKey())->where('estado', 'ACTIVA')
            ->update(['estado' => 'CERRADA', 'cerrada_en' => now()]);
    }
}
