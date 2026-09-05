<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function create(string $name, string $email, string $password): User
    {
        return User::query()->create(compact('name', 'email', 'password'));
    }
}
