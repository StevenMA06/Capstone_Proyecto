<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'rol_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['nombre', 'descripcion', 'estado'];

    public function isManagement(): bool
    {
        return mb_strtolower(trim($this->nombre)) === 'gerencia';
    }
}
