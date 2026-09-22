<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReactivateContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view-office');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'duracion_meses' => [
                'required',
                'integer',
                'between:1,65535',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser hoy o una fecha futura.',
            'duracion_meses.required' => 'La duración en meses es obligatoria.',
            'duracion_meses.integer' => 'La duración debe ser un número entero.',
            'duracion_meses.between' => 'La duración debe estar entre 1 y 65535 meses.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_inicio' => 'fecha de inicio',
            'duracion_meses' => 'duración en meses',
        ];
    }
}
