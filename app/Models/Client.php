<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
    protected $table = 'clientes';

    protected $primaryKey = 'cliente_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'tipo_documento', 'numero_documento', 'nombres_razon_social', 'apellidos',
        'correo', 'telefono', 'direccion', 'referencia_direccion', 'distrito',
        'provincia', 'departamento', 'latitud', 'longitud', 'estado', 'creado_por',
        'numero_referencia', 'ip_router', 'modelo_router', 'usuario_router',
        'contrasena_router', 'ssid', 'contrasena_wifi',
    ];

    protected $hidden = ['contrasena_router', 'contrasena_wifi'];

    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:7', 'longitud' => 'decimal:7',
            'contrasena_router' => 'encrypted', 'contrasena_wifi' => 'encrypted',
        ];
    }
}
