<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-plans') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo_servicio' => ['required', 'string', 'max:80'],
            'velocidad_mbps' => ['nullable', 'integer', 'between:1,4294967295'],
            'precio_mensual' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'condiciones' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return ['numero_plan' => 'número de plan', 'nombre' => 'nombre', 'tipo_servicio' => 'tipo de servicio', 'velocidad_mbps' => 'velocidad (Mbps)', 'precio_mensual' => 'precio mensual', 'descripcion' => 'descripción', 'condiciones' => 'condiciones'];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.', 'string' => 'El campo :attribute debe ser texto.',
            'unique' => 'Este número de plan ya está registrado.', 'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'integer' => 'Ingresa una velocidad entera en Mbps.', 'between' => 'La velocidad debe ser un entero positivo válido.',
            'numeric' => 'Ingresa un precio válido.', 'min' => 'El precio mensual debe ser mayor que cero.',
            'precio_mensual.regex' => 'El precio debe tener como máximo dos decimales, separados por un punto.',
        ];
    }
}
