<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $startDate = now();
        $durationMonths = fake()->numberBetween(1, 12);
        $endDate = (clone $startDate)->addMonths($durationMonths);

        return [
            'codigo' => 'CON-TEST-' . fake()->unique()->numberBetween(1000, 9999),
            'cliente_id' => Client::factory(),
            'plan_id' => ServicePlan::factory(),
            'zona_id' => \App\Models\CoverageZone::factory(),
            'responsable_id' => \App\Models\Employee::factory(), // Debe ser empleado, no usuario
            'fecha_inicio' => $startDate,
            'fecha_fin' => $endDate,
            'duracion_meses' => $durationMonths,
            'precio_contratado' => fake()->randomFloat(2, 50, 500),
            'direccion_servicio' => fake()->address(),
            'estado' => 'ACTIVO',
            'observaciones' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'PENDIENTE',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'ACTIVO',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'CANCELADO',
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'FINALIZADO',
            'fecha_fin' => now()->subDay(),
        ]);
    }
}
