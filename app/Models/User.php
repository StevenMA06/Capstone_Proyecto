<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        return $this->estado === 'ACTIVO' && $this->role?->estado === 'ACTIVO'
            && ($this->empleado_id === null || $this->employee?->estado === 'ACTIVO');
    }

    public function isManager(): bool
    {
        return $this->canAccess() && $this->role->isManagement();
    }

    public function getNameAttribute(): string
    {
        return $this->employee?->full_name ?? $this->nombre_usuario ?? '';
    }
}
