<?php

namespace App\Http\Requests;

use App\Services\SaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-office') ?? false;
    }

    public function rules(): array
    {
        return [
            'cliente' => ['nullable', 'string', 'max:180'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'empleado_id' => ['nullable', 'integer', Rule::exists('empleados', 'empleado_id')],
            'estados' => ['nullable', 'array', 'min:1'],
            'estados.*' => [Rule::in(SaleService::STATES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente' => 'cliente', 'desde' => 'fecha de inicio', 'hasta' => 'fecha de fin',
            'empleado_id' => 'responsable', 'estados' => 'estados', 'estados.*' => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'date_format' => 'Ingresa una :attribute válida.',
            'hasta.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'estados.*.in' => 'Selecciona un estado de venta válido.',
            'empleado_id.exists' => 'Selecciona un responsable válido.',
        ];
    }
}
