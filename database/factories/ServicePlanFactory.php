<?php

namespace Database\Factories;

use App\Models\ServicePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePlanFactory extends Factory
{
    protected $model = ServicePlan::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Plan ' . fake()->words(2, true),
            'descripcion' => fake()->sentence(),
            'precio_mensual' => fake()->randomFloat(2, 50, 500),
            'velocidad_bajada' => fake()->numberBetween(10, 500),
            'velocidad_subida' => fake()->numberBetween(5, 100),
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
