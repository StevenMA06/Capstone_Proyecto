<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('manage-users') ?? false)
            && (! $this->route('user') || $this->user()->can('edit-user', $this->route('user')));
    }

    protected function prepareForValidation(): void
    {
        foreach (['correo', 'nombre_usuario'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::lower(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'nombre_usuario' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('usuarios', 'nombre_usuario')->ignore($user)],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($user)],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'rol_id')->where('estado', 'ACTIVO')->where(fn ($query) => $query->whereIn(DB::raw('LOWER(TRIM(nombre))'), Role::databaseNames($this->user()->isAdministrator() ? Role::NAMES : [Role::MANAGER, Role::OFFICE, Role::TECHNICIAN])))],
            'empleado_id' => ['nullable', 'integer', Rule::exists('empleados', 'empleado_id')->where('estado', 'ACTIVO'), Rule::unique('usuarios', 'empleado_id')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return ['nombre_usuario' => 'nombre de usuario', 'correo' => 'correo electrónico', 'rol_id' => 'rol', 'empleado_id' => 'empleado', 'password' => 'contraseña'];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.', 'unique' => 'El :attribute ya está registrado o asignado.',
            'exists' => 'Selecciona un :attribute válido y activo.', 'integer' => 'Selecciona un :attribute válido.',
            'email' => 'Escribe un correo electrónico válido.', 'max' => 'El campo :attribute supera la longitud permitida (:max).',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.', 'confirmed' => 'La confirmación de la contraseña no coincide.',
            'nombre_usuario.regex' => 'Usa letras sin acentos, números, puntos, guiones o guiones bajos.',
        ];
    }
}
