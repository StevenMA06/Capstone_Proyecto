<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Repositories\ClientRepository;
use Illuminate\Support\Arr;

class ClientService
{
    public function __construct(private ClientRepository $clients) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): Client
    {
        $attributes = Arr::only($data, [
            'tipo_documento', 'numero_documento', 'nombres_razon_social', 'apellidos',
            'correo', 'telefono', 'direccion', 'referencia_direccion', 'distrito',
            'provincia', 'departamento', 'latitud', 'longitud',
        ]);

        return $this->clients->create($attributes + [
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);
    }
}
