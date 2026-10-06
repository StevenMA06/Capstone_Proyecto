<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'rol_id' => fn () => Role::firstOrCreate(['nombre' => Role::OFFICE], ['estado' => 'ACTIVO'])->getKey(),
            'nombre_usuario' => fake()->unique()->userName(), 'correo' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('ClaveSegura12345'), 'estado' => 'ACTIVO',
        ];
    }
}
