<?php

namespace Tests;

use App\Models\User;
use App\Repositories\AccessRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        if ($app['config']->get('app.env') !== 'testing' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Las pruebas solo se ejecutan en una base cuyo nombre termina en _test.');
        }

        return $app;
    }

    protected function signIn(User $user): static
    {
        $id = app(AccessRepository::class)->begin($user, '127.0.0.1', 'PHPUnit');
        $this->actingAs($user)->withSession(['login_session_id' => $id]);

        return $this;
    }
}
