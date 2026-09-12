<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClientRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Client
    {
        return Client::create($attributes);
    }

    public function search(string $search, ?string $state): LengthAwarePaginator
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        return Client::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("CONCAT_WS(' ', nombres_razon_social, apellidos) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("numero_documento LIKE ? ESCAPE '!'", [$pattern]);
            }))
            ->when($state, fn ($query) => $query->where('estado', $state))
            ->orderBy('nombres_razon_social')->orderBy('cliente_id')->paginate(15)->withQueryString();
    }

    /** @param array<string, mixed> $attributes */
    public function update(Client $client, array $attributes): Client
    {
        $client->fill($attributes)->save();

        return $client->refresh();
    }

    public function deactivate(Client $client): Client
    {
        Client::whereKey($client->getKey())->where('estado', 'ACTIVO')->update(['estado' => 'INACTIVO']);

        return $client->refresh();
    }

    public function contracts(Client $client): LengthAwarePaginator
    {
        return DB::table('contratos as c')->join('planes_servicio as p', 'p.plan_id', '=', 'c.plan_id')
            ->where('c.cliente_id', $client->getKey())
            ->select('c.*', 'p.nombre as plan_nombre')
            ->orderByDesc('c.fecha_registro')->orderByDesc('c.contrato_id')->paginate(15)->withQueryString();
    }

    public function payments(Client $client): LengthAwarePaginator
    {
        return DB::table('pagos as p')->join('contratos as c', 'c.contrato_id', '=', 'p.contrato_id')
            ->where('c.cliente_id', $client->getKey())
            ->select('p.*', 'c.codigo as contrato_codigo')
            ->orderByDesc('p.creado_en')->orderByDesc('p.pago_id')->paginate(15)->withQueryString();
    }

    public function installations(Client $client): LengthAwarePaginator
    {
        return DB::table('pedidos_instalacion as i')->join('contratos as c', 'c.contrato_id', '=', 'i.contrato_id')
            ->leftJoin('empleados as e', 'e.empleado_id', '=', 'i.tecnico_id')
            ->where('i.cliente_id', $client->getKey())->where('c.cliente_id', $client->getKey())
            ->select('i.*', 'c.codigo as contrato_codigo', 'e.nombres as tecnico_nombres', 'e.apellidos as tecnico_apellidos')
            ->orderByDesc('i.fecha_solicitud')->orderByDesc('i.pedido_id')->paginate(15)->withQueryString();
    }
}
