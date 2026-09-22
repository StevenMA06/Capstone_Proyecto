<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractPeriodFactory extends Factory
{
    protected $model = ContractPeriod::class;

    public function definition(): array
    {
        $startDate = now();
        $durationMonths = fake()->numberBetween(1, 12);
        $endDate = (clone $startDate)->addMonths($durationMonths);

        return [
            'contrato_id' => Contract::factory(),
            'numero_periodo' => 1,
            'fecha_inicio_periodo' => $startDate,
            'fecha_fin_periodo' => $endDate,
            'duracion_meses_periodo' => $durationMonths,
            'estado_periodo' => 'ACTIVO',
            'motivo_cancelacion' => null,
            'fecha_cancelacion' => null,
            'cancelado_por' => null,
            'origen_cancelacion' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_periodo' => 'ACTIVO',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_periodo' => 'CANCELADO',
            'fecha_cancelacion' => now(),
            'motivo_cancelacion' => 'Cancelación de prueba',
            'origen_cancelacion' => 'MANUAL',
            'cancelado_por' => User::factory(),
        ]);
    }

    public function cancelledByNonPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_periodo' => 'CANCELADO',
            'fecha_cancelacion' => now(),
            'motivo_cancelacion' => 'Falta de pago',
            'origen_cancelacion' => 'AUTOMATICO_FALTA_PAGO',
            'cancelado_por' => null,
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_periodo' => 'FINALIZADO',
            'fecha_fin_periodo' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_periodo' => 'ACTIVO',
            'fecha_fin_periodo' => now()->subDays(5),
        ]);
    }
}
