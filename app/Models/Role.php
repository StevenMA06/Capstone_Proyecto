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

    private const ALIASES = [
        'administrador' => self::ADMIN, 'admin' => self::ADMIN,
        'gerente' => self::MANAGER, 'gerencia' => self::MANAGER,
        'usuario de oficina' => self::OFFICE, 'usuario de oficinas' => self::OFFICE,
        'personal autorizado' => self::OFFICE, 'personal de oficina' => self::OFFICE, 'personal de oficinas' => self::OFFICE,
        'técnico' => self::TECHNICIAN, 'tecnico' => self::TECHNICIAN,
        'técnico de campo' => self::TECHNICIAN, 'tecnico de campo' => self::TECHNICIAN,
    ];

    public function getNombreAttribute(string $value): string
    {
        return self::ALIASES[mb_strtolower(trim($value))] ?? $value;
    }

    public static function databaseNames(array $roles = self::NAMES): array
    {
        return array_keys(array_filter(self::ALIASES, fn ($name) => in_array($name, $roles, true)));
    }

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
