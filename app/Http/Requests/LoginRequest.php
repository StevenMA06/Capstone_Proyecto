<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.string' => 'El correo debe ser un texto.',
            'email.max' => 'El correo es demasiado largo.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'La contraseña debe ser un texto.',
            'password.max' => 'La contraseña es demasiado larga.',
            'remember.boolean' => 'La opción de recordar sesión no es válida.',
        ];
    }
}
