<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCoverageZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-zones') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'distrito' => ['required', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer', 'distinct', Rule::exists('planes_servicio', 'plan_id')->where('estado', 'ACTIVO')],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre', 'distrito' => 'distrito',
            'provincia' => 'provincia', 'departamento' => 'departamento',
            'descripcion' => 'descripción', 'plan_ids' => 'planes disponibles',
            'plan_ids.*' => 'plan seleccionado',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'plan_ids.*.exists' => 'Uno de los planes seleccionados ya no está activo.',
        ];
    }
}
