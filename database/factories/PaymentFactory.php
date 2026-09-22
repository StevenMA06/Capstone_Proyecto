<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $vencimiento = now()->addDays(fake()->numberBetween(1, 30));
        
        return [
            'codigo' => 'PAG-TEST-' . fake()->unique()->numberBetween(10000, 99999),
            'contrato_id' => Contract::factory(),
            'monto' => fake()->randomFloat(2, 50, 500),
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => $vencimiento,
            'fecha_pago' => null,
            'metodo_pago' => null,
            'referencia_externa' => null,
            'estado' => 'PENDIENTE',
            'observaciones' => fake()->optional()->sentence(),
            'registrado_por' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'PENDIENTE',
            'fecha_pago' => null,
            'metodo_pago' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(3),
            'fecha_pago' => null,
            'metodo_pago' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'ANULADO',
            'fecha_vencimiento' => now()->subDays(10),
            'fecha_pago' => null,
            'metodo_pago' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'REALIZADO',
            'fecha_pago' => now()->subDays(1),
            'metodo_pago' => fake()->randomElement(['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN']),
        ]);
    }

    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5), // Dentro de 7 días
        ]);
    }
}
