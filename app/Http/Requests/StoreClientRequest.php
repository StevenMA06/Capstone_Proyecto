<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('register-clients') ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['DNI', 'CE', 'RUC', 'OTRO'])],
            'numero_documento' => ['required', 'string', 'max:20'],
            'nombres_razon_social' => ['required', 'string', 'max:180'],
            'apellidos' => ['nullable', 'string', 'max:120'],
            'correo' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:25'],
            'direccion' => ['required', 'string', 'max:255'],
            'referencia_direccion' => ['nullable', 'string', 'max:255'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_documento' => 'tipo de documento', 'numero_documento' => 'número de documento',
            'nombres_razon_social' => 'nombres o razón social', 'direccion' => 'dirección',
            'referencia_direccion' => 'referencia de dirección',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'in' => 'Selecciona un tipo de documento válido.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
        ];
    }
}
