<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const ADMIN = 'Administrador';

    public const MANAGER = 'Gerente';

    public const OFFICE = 'Usuario de oficina';

    public const TECHNICIAN = 'Técnico';

    public const NAMES = [self::ADMIN, self::MANAGER, self::OFFICE, self::TECHNICIAN];

    protected $table = 'roles';

    protected $primaryKey = 'rol_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['nombre', 'descripcion', 'estado'];

    public function isManagement(): bool
    {
        return in_array($this->nombre, [self::ADMIN, self::MANAGER], true);
    }
}
