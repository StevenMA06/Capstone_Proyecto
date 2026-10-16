<?php

namespace Database\Factories;

use App\Models\CoverageZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoverageZoneFactory extends Factory
{
    protected $model = CoverageZone::class;

    public function definition(): array
    {
        $distrito = fake()->randomElement(['San Isidro', 'Miraflores', 'Surco', 'San Borja', 'La Molina']);
        
        return [
            'codigo' => 'ZONA-' . fake()->unique()->numerify('###'),
            'nombre' => 'Zona ' . $distrito,
            'distrito' => $distrito,
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'estado' => 'ACTIVA',
            'creado_por' => \App\Models\User::factory(),
        ];
    }
}
