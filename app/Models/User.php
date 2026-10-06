<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'usuario_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['empleado_id', 'rol_id', 'nombre_usuario', 'correo', 'password_hash', 'estado', 'ultimo_acceso_en'];

    protected $hidden = ['password_hash'];

    protected $rememberTokenName = '';

    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if ($user->wasRecentlyCreated || $user->wasChanged('rol_id')) {
                $old = $user->getOriginal('rol_id');
                if ($old && $old != $user->rol_id) $user->roles()->detach($old);
                $user->roles()->syncWithoutDetaching([$user->rol_id]);
            }
        });
        static::deleting(function (User $user): void {
            if ($user->hasRole(Role::ADMIN, false)) {
                throw ValidationException::withMessages(['account' => 'La cuenta Administrador no se puede eliminar.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['password_hash' => 'hashed', 'ultimo_acceso_en' => 'datetime'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id', 'rol_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'empleado_id', 'empleado_id');
    }

    public function canAccess(): bool
    {
        return $this->estado === 'ACTIVO' && $this->roles()->where('estado', 'ACTIVO')->exists()
            && ($this->empleado_id === null || ($this->employee?->isEligible() ?? false));
    }

    public function isManager(): bool
    {
        return $this->canAccess() && ($this->hasRole(Role::ADMIN) || $this->hasRole(Role::MANAGER));
    }

    public function isAdministrator(): bool
    {
        return $this->canAccess() && $this->hasRole(Role::ADMIN);
    }

    public function isOffice(): bool
    {
        return $this->canAccess() && $this->hasRole(Role::OFFICE);
    }

    public function isTechnician(): bool
    {
        return $this->canAccess() && $this->hasRole(Role::TECHNICIAN);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'usuario_rol', 'usuario_id', 'rol_id');
    }

    public function hasRole(string $name, bool $activeOnly = true): bool
    {
        return $this->roles()->where('nombre', $name)->when($activeOnly, fn ($q) => $q->where('estado', 'ACTIVO'))->exists();
    }

    public function hasPermission(string $code): bool
    {
        if (! $this->canAccess() || ! isset(\App\Support\AccessCatalog::permissions()[$code])) return false;
        if ($this->isAdministrator()) return true;
        if (\App\Support\AccessCatalog::reserved($code)) return false;
        return \Illuminate\Support\Facades\DB::table('usuario_rol as ur')
            ->join('roles as r', 'r.rol_id', '=', 'ur.rol_id')
            ->join('rol_permiso as rp', 'rp.rol_id', '=', 'r.rol_id')
            ->join('permisos as p', 'p.permiso_id', '=', 'rp.permiso_id')
            ->where('ur.usuario_id', $this->getKey())->where('r.estado', 'ACTIVO')
            ->where('p.estado', 'ACTIVO')->where('rp.permitido', true)->where('p.codigo', $code)->exists();
    }

    public function getRoleNamesAttribute(): string
    {
        return $this->roles()->where('estado', 'ACTIVO')->orderBy('nombre')->pluck('nombre')->join(', ');
    }

    public function getNameAttribute(): string
    {
        return $this->employee?->full_name ?? $this->nombre_usuario ?? '';
    }
}
