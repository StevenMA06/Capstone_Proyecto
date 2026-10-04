<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleAccessService
{
    public function saveRole(User $actor, ?Role $role, array $data): Role
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        return DB::transaction(function () use ($actor, $role, $data) {
            Role::orderBy('rol_id')->lockForUpdate()->get();
            $record = $role ? Role::findOrFail($role->getKey()) : new Role;
            if ($record->exists && in_array($record->nombre, [Role::ADMIN, Role::MANAGER], true)) {
                throw ValidationException::withMessages(['nombre' => 'Administrador y Gerente son roles protegidos con acceso completo.']);
            }
            $before = $record->exists ? $this->snapshot($record) : null;
            $record->fill(collect($data)->only(['nombre', 'descripcion', 'estado'])->all())->save();
            $codes = $data['permissions'] ?? [];
            foreach ($codes as $code) {
                if (str_ends_with($code, '.manage') || $code === 'technician.supervise') {
                    $view = explode('.', $code)[0].'.view';
                    if (! in_array($view, $codes)) throw ValidationException::withMessages(['permissions' => 'Para administrar o supervisar un módulo debes habilitar también su consulta.']);
                }
            }
            DB::table('rol_permiso')->where('rol_id', $record->getKey())->delete();
            foreach (DB::table('permisos')->whereIn('codigo', $codes)->where('estado', 'ACTIVO')->pluck('permiso_id') as $id) {
                DB::table('rol_permiso')->insert(['rol_id' => $record->getKey(), 'permiso_id' => $id, 'permitido' => true]);
            }
            $this->ensureAdministrator();
            $this->audit($actor, 'CONFIGURAR_ROL', 'rol', $record->getKey(), $before, $this->snapshot($record));
            return $record;
        }, 3);
    }

    public function assign(User $actor, User $account, array $ids): void
    {
        abort_unless($actor->hasPermission('user-roles.manage'), 403);
        DB::transaction(function () use ($actor, $account, $ids) {
            Role::orderBy('rol_id')->lockForUpdate()->get();
            $account = User::lockForUpdate()->findOrFail($account->getKey());
            abort_if($account->hasRole(Role::ADMIN, false) && ! $actor->isAdministrator(), 403);
            $roles = Role::whereIn('rol_id', $ids)->where('estado', 'ACTIVO')->get();
            if ($roles->count() !== count(array_unique($ids)) || $roles->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'Selecciona al menos un rol activo.']);
            }
            if ($roles->contains('nombre', Role::ADMIN) && ! $actor->isAdministrator()) abort(403);
            if ($account->hasRole(Role::ADMIN, false) && ! $roles->contains('nombre', Role::ADMIN)) {
                throw ValidationException::withMessages(['roles' => 'No puedes retirar el rol del propietario Administrador.']);
            }
            $before = $account->roles()->pluck('roles.rol_id')->all();
            // Preserve the legacy primary column for existing integrations, without using it for authorization.
            $primary = $roles->firstWhere('nombre', Role::ADMIN) ?? $roles->firstWhere('rol_id', $account->rol_id) ?? $roles->first();
            $account->forceFill(['rol_id' => $primary->getKey()])->save();
            $account->roles()->sync($ids);
            $this->ensureAdministrator();
            $this->audit($actor, 'ASIGNAR_ROLES', 'usuario', $account->getKey(), $before, array_map('intval', $ids));
        }, 3);
    }

    public function ensureAdministrator(): void
    {
        $available = User::where('estado', 'ACTIVO')->get()->contains(fn ($u) => $u->canAccess()
            && $u->hasPermission('users.manage') && $u->hasPermission('roles.manage') && $u->hasPermission('user-roles.manage'));
        if (! $available) throw ValidationException::withMessages(['roles' => 'Debe quedar al menos una cuenta vigente capaz de administrar usuarios, roles y permisos.']);
    }

    public function snapshot(Role $role): array
    {
        return $role->only(['nombre', 'descripcion', 'estado']) + ['permissions' => DB::table('rol_permiso as rp')
            ->join('permisos as p', 'p.permiso_id', '=', 'rp.permiso_id')->where('rp.rol_id', $role->getKey())
            ->where('rp.permitido', true)->orderBy('p.codigo')->pluck('p.codigo')->all()];
    }

    public function audit(?User $actor, string $action, string $entity, int $id, $before, $after): void
    {
        DB::table('auditoria_permisos')->insert(['actor_id' => $actor?->getKey(), 'accion' => $action,
            'entidad' => $entity, 'entidad_id' => $id, 'antes' => json_encode($before), 'despues' => json_encode($after), 'fecha_hora' => now()]);
    }
}
