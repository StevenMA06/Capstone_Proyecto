<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Repositories\ClientRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ClientService
{
    public function __construct(private ClientRepository $clients) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): Client
    {
        return $this->clients->create($this->attributes($data) + [
            'estado' => 'PENDIENTE',
            'creado_por' => $creator->getKey(),
        ]);
    }

    public function search(string $search, ?string $state): LengthAwarePaginator
    {
        return $this->clients->search($search, $state);
    }

    /** @param array<string, mixed> $data */
    public function update(Client $client, array $data): Client
    {
        foreach (['contrasena_router', 'contrasena_wifi'] as $secret) {
            if (! isset($data[$secret]) || $data[$secret] === '') {
                unset($data[$secret]);
            }
        }
        return $this->clients->update($client, $this->attributes($data));
    }

    public function deactivate(Client $client): Client
    {
        return $this->clients->deactivate($client);
    }

    public function history(Client $client, string $tab): LengthAwarePaginator
    {
        return match ($tab) {
            'pagos' => $this->clients->payments($client),
            'instalaciones' => $this->clients->installations($client),
            default => $this->clients->contracts($client),
        };
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'tipo_documento', 'numero_documento', 'nombres_razon_social', 'apellidos',
            'correo', 'telefono', 'direccion', 'referencia_direccion', 'distrito',
            'provincia', 'departamento', 'latitud', 'longitud',
            'numero_referencia', 'ip_router', 'modelo_router', 'usuario_router',
            'contrasena_router', 'ssid', 'contrasena_wifi',
        ]);
    }
}
