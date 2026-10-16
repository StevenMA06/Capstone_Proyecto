<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoverageZone extends Model
{
    use HasFactory;
    protected $table = 'zonas_cobertura';

    protected $primaryKey = 'zona_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'codigo', 'nombre', 'distrito', 'provincia', 'departamento',
        'descripcion', 'estado', 'creado_por',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }

    public function plans()
    {
        return $this->belongsToMany(ServicePlan::class, 'plan_zona_cobertura', 'zona_id', 'plan_id')
            ->withPivot(['estado', 'asignado_en']);
    }
}
