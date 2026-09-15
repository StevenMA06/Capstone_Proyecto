<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-office') ?? false;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:40', Rule::unique('contratos', 'codigo')],
            'plan_id' => ['required', 'integer', Rule::exists('planes_servicio', 'plan_id')->where('estado', 'ACTIVO')],
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'cliente_id')->where('estado', 'ACTIVO')],
            'zona_id' => ['required', 'integer', Rule::exists('zonas_cobertura', 'zona_id')->where('estado', 'ACTIVA')],
            'responsable_id' => ['required', 'integer', Rule::exists('empleados', 'empleado_id')->where('estado', 'ACTIVO')],
            'fecha_inicio' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'duracion_meses' => ['required', 'integer', 'between:1,65535'],
            'direccion_servicio' => ['required', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return ['codigo' => 'código', 'plan_id' => 'plan', 'cliente_id' => 'cliente', 'zona_id' => 'zona', 'responsable_id' => 'responsable', 'fecha_inicio' => 'fecha de inicio', 'duracion_meses' => 'duración en meses', 'direccion_servicio' => 'dirección de servicio'];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.', 'exists' => 'Selecciona un :attribute activo y válido.',
            'integer' => 'El campo :attribute debe ser un número entero.', 'unique' => 'Este código de contrato ya está registrado.',
            'date_format' => 'Ingresa una fecha válida (año-mes-día).', 'between' => 'La duración debe estar entre 1 y 65535 meses.',
            'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute supera el máximo permitido (:max).',
        ];
    }
}
