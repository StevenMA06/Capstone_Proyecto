<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePlan extends Model
{
    protected $table = 'planes_servicio';

    protected $primaryKey = 'plan_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['numero_plan', 'nombre', 'tipo_servicio', 'velocidad_mbps', 'precio_mensual', 'condiciones', 'descripcion', 'estado', 'creado_por'];

    protected function casts(): array
    {
        return ['precio_mensual' => 'decimal:2', 'velocidad_mbps' => 'integer'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }
}
