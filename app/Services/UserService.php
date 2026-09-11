<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private UserRepository $users) {}

    public function search(string $search = '', ?int $role = null): LengthAwarePaginator
    {
        return $this->users->paginate($search, $role);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return $this->users->create($this->attributes($data) + ['estado' => 'ACTIVO']);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $managers = $this->users->lockManagers();
            $locked = $this->users->lock($user->getKey());
            $role = Role::findOrFail($data['rol_id']);
            $employee = ! empty($data['empleado_id']) ? Employee::findOrFail($data['empleado_id']) : null;
            $willManage = $locked->estado === 'ACTIVO' && $role->estado === 'ACTIVO' && $role->isManagement()
                && (! $employee || $employee->estado === 'ACTIVO');
            $this->protectLastManager($locked, $willManage, $managers->filter(fn ($u) => $u->isManager())->count());
            $updated = $this->users->update($locked, $this->attributes($data));
            if (! empty($data['password']) || $updated->wasChanged(['rol_id', 'empleado_id'])) {
                $this->users->revokeSessions($updated);
            }

            return $updated;
        });
    }

    public function setActive(User $user, bool $active): User
    {
        return DB::transaction(function () use ($user, $active): User {
            $managers = $this->users->lockManagers();
            $locked = $this->users->lock($user->getKey());
            $this->protectLastManager($locked, $active, $managers->filter(fn ($u) => $u->isManager())->count());
            $updated = $this->users->update($locked, ['estado' => $active ? 'ACTIVO' : 'INACTIVO']);
            if (! $active) {
                $this->users->revokeSessions($updated);
            }

            return $updated;
        });
    }

    private function protectLastManager(User $user, bool $willManage, int $managers): void
    {
        if ($user->isManager() && ! $willManage && $managers <= 1) {
            throw ValidationException::withMessages(['rol_id' => 'Debe quedar al menos una cuenta activa de Gerencia.']);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        $attributes = [
            'nombre_usuario' => $data['nombre_usuario'], 'correo' => $data['correo'],
            'rol_id' => $data['rol_id'], 'empleado_id' => $data['empleado_id'] ?? null,
        ];
        if (! empty($data['password'])) {
            $attributes['password_hash'] = $data['password'];
        }

        return $attributes;
    }
}
