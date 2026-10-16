<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'tipo_documento' => 'DNI',
            'numero_documento' => fake()->numerify('########'),
            'cargo' => fake()->randomElement(Employee::POSITIONS),
            'fecha_ingreso' => now()->subMonths(12)->toDateString(),
            'fecha_cese' => null,
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('9########'),
            'estado' => 'ACTIVO',
        ];
    }
}
