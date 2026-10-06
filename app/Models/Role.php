<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const ADMIN = 'Administrador';

    public const MANAGER = 'Gerencia';

    public const OFFICE = 'Call Center';

    public const TECHNICIAN = 'Técnico de campo';

    public const WAREHOUSE = 'Jefe de Almacén';
    public const IT = 'Jefe de TI';
    public const INSTALLATIONS = 'Jefe de Instalaciones';

    public const NAMES = [self::ADMIN, self::MANAGER, self::OFFICE, self::TECHNICIAN, self::WAREHOUSE, self::IT, self::INSTALLATIONS];

    protected $table = 'roles';

    protected $primaryKey = 'rol_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['nombre', 'descripcion', 'estado'];

    protected static function booted(): void
    {
        static::created(fn (Role $role) => \App\Support\AccessCatalog::seedRole($role));
    }

    public function isManagement(): bool
    {
        return in_array($this->nombre, [self::ADMIN, self::MANAGER], true);
    }
}
