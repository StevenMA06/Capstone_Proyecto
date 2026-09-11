<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'clientes';

    protected $primaryKey = 'cliente_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'tipo_documento', 'numero_documento', 'nombres_razon_social', 'apellidos',
        'correo', 'telefono', 'direccion', 'referencia_direccion', 'distrito',
        'provincia', 'departamento', 'latitud', 'longitud', 'estado', 'creado_por',
    ];

    protected function casts(): array
    {
        return ['latitud' => 'decimal:7', 'longitud' => 'decimal:7'];
    }
}
