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
            'correo' => ['required', 'string', 'email', 'max:150'],
            'telefono' => ['required', 'string', 'max:25'],
            'direccion' => ['required', 'string', 'max:255'],
            'referencia_direccion' => ['nullable', 'string', 'max:255'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'numero_referencia' => ['nullable', 'string', 'max:25'],
            'ip_router' => ['required', 'string', 'ip', 'max:45'],
            'modelo_router' => ['required', 'string', 'max:100'],
            'usuario_router' => ['required', 'string', 'max:100'],
            'ssid' => ['required', 'string', 'max:100'],
            'contrasena_router' => $this->passwordRules($client, 'contrasena_router'),
            'contrasena_wifi' => $this->passwordRules($client, 'contrasena_wifi'),
        ];
    }

    private function passwordRules(mixed $client, string $field): array
    {
        $configured = $client instanceof Client && filled($client->getRawOriginal($field));

        return ['bail', $configured ? 'nullable' : 'required', 'string', 'max:128'];
    }

    protected function prepareForValidation(): void
    {
        foreach (['contrasena_router', 'contrasena_wifi'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && trim($value) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function attributes(): array
    {
        return [
            'tipo_documento' => 'tipo de documento', 'numero_documento' => 'número de documento',
            'nombres_razon_social' => 'nombres o razón social', 'direccion' => 'dirección',
            'referencia_direccion' => 'referencia de dirección',
            'telefono' => 'teléfono',
            'numero_referencia' => 'número de referencia', 'ip_router' => 'IP del router',
            'modelo_router' => 'modelo del router', 'usuario_router' => 'usuario del router',
            'contrasena_router' => 'contraseña del router', 'ssid' => 'SSID',
            'contrasena_wifi' => 'contraseña WiFi',
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
            'ip' => 'Escribe una dirección IP válida para el router.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
        ];
    }
}
