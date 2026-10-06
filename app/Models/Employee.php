<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    public const POSITIONS = ['Técnico de Campo', 'Asistente de ventas', 'Jefe del Área de TI', 'Jefe de Instalación', 'Jefe de Almacén'];

    protected $table = 'empleados';

    protected $primaryKey = 'empleado_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $guarded = ['empleado_id'];

    public static function today(): string
    {
        return now('America/Lima')->toDateString();
    }

    public function scopeEligible($query)
    {
        return $query->where('estado', 'ACTIVO')
            ->where(fn ($q) => $q->whereNull('fecha_cese')->orWhere('fecha_cese', '>=', self::today()))
            ->where(fn ($q) => $q->whereNull('fecha_ingreso')->orWhere('fecha_ingreso', '<=', self::today()));
    }

    public function hasEnded(): bool
    {
        return $this->fecha_cese !== null && $this->fecha_cese < self::today();
    }

    public function isEligible(): bool
    {
        return $this->estado === 'ACTIVO' && ! $this->hasEnded()
            && ($this->fecha_ingreso === null || $this->fecha_ingreso <= self::today());
    }

    public function accounts()
    {
        return $this->hasMany(User::class, 'empleado_id', 'empleado_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }
}
