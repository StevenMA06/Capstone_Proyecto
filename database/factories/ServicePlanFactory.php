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
            'numero_plan' => 'PLAN-' . fake()->unique()->numerify('###'),
            'nombre' => 'Plan ' . fake()->words(2, true),
            'tipo_servicio' => fake()->randomElement(['RESIDENCIAL', 'EMPRESARIAL', 'CORPORATIVO']),
            'velocidad_mbps' => fake()->randomElement([10, 20, 50, 100, 200, 500]),
            'precio_mensual' => fake()->randomFloat(2, 50, 500),
            'descripcion' => fake()->sentence(),
            'estado' => 'ACTIVO',
            'creado_por' => \App\Models\User::factory(),
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
