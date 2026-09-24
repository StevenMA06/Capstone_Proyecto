<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-employees')
            && (! $this->route('employee') || $this->user()->can('edit-employee', $this->route('employee')));
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['DNI', 'CE', 'OTRO'])],
            'numero_documento' => ['required', 'string', 'max:20', $this->input('tipo_documento') === 'DNI' ? 'regex:/^[0-9]{8}$/' : 'regex:/^[a-zA-Z0-9-]+$/', Rule::unique('empleados')->where('tipo_documento', $this->input('tipo_documento'))->ignore($this->route('employee'))],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:120'],
            'cargo' => ['required', 'string', Rule::in(Employee::POSITIONS)],
            'correo' => ['required', 'email', 'max:150', Rule::unique('empleados')->ignore($this->route('employee'))],
            'telefono' => ['required', 'string', 'max:25', 'regex:/^[0-9+() -]+$/'],
            'fecha_ingreso' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
            'fecha_cese' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_ingreso'],
        ];
    }

    public function attributes(): array
    {
        return ['tipo_documento' => 'tipo de documento', 'numero_documento' => 'número de documento', 'fecha_ingreso' => 'fecha de inicio', 'fecha_cese' => 'fecha de cese', 'correo' => 'correo electrónico', 'telefono' => 'teléfono'];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'unique' => 'El :attribute ya está registrado.', 'max' => 'El campo :attribute admite hasta :max caracteres.', 'date_format' => 'Introduce una fecha válida en :attribute.', 'after_or_equal' => 'La fecha de :attribute debe ser igual o posterior a :date.', 'numero_documento.regex' => 'El DNI debe tener 8 dígitos; otros documentos admiten letras, números y guiones.', 'email' => 'Introduce un correo electrónico válido.', 'telefono.regex' => 'Introduce un teléfono válido.', 'in' => 'Selecciona un :attribute válido.'];
    }
}
