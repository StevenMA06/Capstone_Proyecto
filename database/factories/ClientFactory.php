<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $tipoDocumento = fake()->randomElement(['DNI', 'RUC', 'CE']);
        $numeroDocumento = $tipoDocumento === 'RUC' 
            ? fake()->numerify('20#########') 
            : fake()->numerify('########');

        return [
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'nombres_razon_social' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('9########'),
            'direccion' => fake()->address(),
            'referencia' => fake()->optional()->sentence(),
            'estado' => 'ACTIVO',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'ACTIVO',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'INACTIVO',
        ]);
    }
}
