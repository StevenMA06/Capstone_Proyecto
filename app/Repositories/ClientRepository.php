<?php

namespace App\Repositories;

use App\Models\Client;

class ClientRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Client
    {
        return Client::create($attributes);
    }
}
