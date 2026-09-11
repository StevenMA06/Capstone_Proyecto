<?php

namespace App\Auth;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;

class RepositoryUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, private UserRepository $users)
    {
        parent::__construct($hasher, User::class);
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! isset($credentials['login']) || ! is_string($credentials['login'])) {
            return null;
        }

        return $this->users->findByLogin($credentials['login']);
    }
}
