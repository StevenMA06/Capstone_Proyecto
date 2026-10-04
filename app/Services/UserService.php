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
        return DB::transaction(function () use ($data) {
            $this->eligibleEmployee($data['empleado_id'] ?? null);

            $user = $this->users->create($this->attributes($data) + ['estado' => 'ACTIVO']);
            app(RoleAccessService::class)->audit(auth()->user(), 'ASIGNAR_ROLES', 'usuario', $user->getKey(), [], [$user->rol_id]);
            return $user;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            Role::orderBy('rol_id')->lockForUpdate()->get();
            $wasAccessAdmin = $user->hasPermission('users.manage') && $user->hasPermission('roles.manage') && $user->hasPermission('user-roles.manage');
            $managers = $this->users->lockManagers();
            $locked = $this->users->lock($user->getKey());
            $beforeRoles = $locked->roles()->pluck('roles.rol_id')->all();
            $role = Role::findOrFail($data['rol_id']);
            if ($locked->hasRole(Role::ADMIN, false) && $role->nombre !== Role::ADMIN) {
                throw ValidationException::withMessages(['rol_id' => 'El rol Administrador no se puede cambiar.']);
            }
            $employee = $this->eligibleEmployee($data['empleado_id'] ?? null, $locked->estado !== 'ACTIVO' && (string) ($data['empleado_id'] ?? '') === (string) $locked->empleado_id);
            $willManage = $locked->estado === 'ACTIVO' && $role->estado === 'ACTIVO' && $role->isManagement()
                && (! $employee || $employee->estado === 'ACTIVO');
            $this->protectLastManager($locked, $willManage, $managers->filter(fn ($u) => $u->isManager())->count());
            $updated = $this->users->update($locked, $this->attributes($data));
            $afterRoles = $updated->roles()->pluck('roles.rol_id')->all();
            if ($beforeRoles !== $afterRoles) app(RoleAccessService::class)->audit(auth()->user(), 'ASIGNAR_ROLES', 'usuario', $updated->getKey(), $beforeRoles, $afterRoles);
            if ($wasAccessAdmin) app(RoleAccessService::class)->ensureAdministrator();
            if (! empty($data['password']) || $updated->wasChanged(['rol_id', 'empleado_id'])) {
                $this->users->revokeSessions($updated);
            }

            return $updated;
        }, 3);
    }

    public function setActive(User $user, bool $active): User
    {
        return DB::transaction(function () use ($user, $active): User {
            Role::orderBy('rol_id')->lockForUpdate()->get();
            $wasAccessAdmin = $user->hasPermission('users.manage') && $user->hasPermission('roles.manage') && $user->hasPermission('user-roles.manage');
            $managers = $this->users->lockManagers();
            $locked = $this->users->lock($user->getKey());
            if ($active) {
                $this->eligibleEmployee($locked->empleado_id);
            }
            if ($locked->hasRole(Role::ADMIN, false) && ! $active) {
                throw ValidationException::withMessages(['rol_id' => 'La cuenta Administrador no se puede inhabilitar.']);
            }
            $this->protectLastManager($locked, $active, $managers->filter(fn ($u) => $u->isManager())->count());
            $updated = $this->users->update($locked, ['estado' => $active ? 'ACTIVO' : 'INACTIVO']);
            if ($wasAccessAdmin) app(RoleAccessService::class)->ensureAdministrator();
            if (! $active) {
                $this->users->revokeSessions($updated);
            }

            return $updated;
        }, 3);
    }

    private function eligibleEmployee($id, bool $preserveExisting = false): ?Employee
    {
        if (! $id) {
            return null;
        }
        $employee = Employee::lockForUpdate()->find($id);
        if (! $employee || (! $preserveExisting && ! $employee->isEligible())) {
            throw ValidationException::withMessages(['empleado_id' => 'Selecciona un empleado activo con vínculo laboral vigente.']);
        }

        return $employee;
    }

    private function protectLastManager(User $user, bool $willManage, int $managers): void
    {
        if ($user->isManager() && ! $willManage && $managers <= 1) {
            throw ValidationException::withMessages(['rol_id' => 'Debe quedar al menos una cuenta activa de Administrador o Gerente.']);
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
