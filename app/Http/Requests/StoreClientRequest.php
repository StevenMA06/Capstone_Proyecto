<?php

namespace App\Http\Requests;

use App\Models\Client;
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
        $client = $this->route('client');
        $document = Rule::unique('clientes', 'numero_documento')
            ->where('tipo_documento', is_string($this->input('tipo_documento')) ? $this->input('tipo_documento') : null);
        if ($client instanceof Client) {
            $document->ignore($client);
        }

        return [
            'tipo_documento' => ['bail', 'required', 'string', Rule::in(['DNI', 'CE', 'RUC', 'OTRO'])],
            'numero_documento' => [
                'bail', 'required', 'string', 'max:20',
                Rule::when($this->input('tipo_documento') === 'DNI', ['regex:/^[0-9]{8}$/D']),
                $document,
            ],
            'nombres_razon_social' => ['required', 'string', 'max:180'],
            'apellidos' => ['required', 'string', 'max:120'],
            'correo' => ['nullable', 'string', 'email', 'max:150'],
            'telefono' => ['required', 'string', 'max:25'],
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
            'telefono' => 'teléfono',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'in' => 'Selecciona un tipo de documento válido.',
            'unique' => 'Este número ya está registrado para el tipo de documento seleccionado.',
            'numero_documento.regex' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
            'email' => 'Escribe un correo electrónico válido.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
        ];
    }
}
