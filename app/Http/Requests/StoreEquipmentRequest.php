<?php

namespace App\Http\Requests;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $equipment = $this->route('equipment');

        $codigoRule = Rule::unique('equipos', 'codigo');
        $serieRule = Rule::unique('equipos', 'numero_serie');

        if ($equipment instanceof Equipment) {
            $codigoRule->ignore($equipment);
            $serieRule->ignore($equipment);
        }

        return [
            'codigo' => ['required', 'string', 'max:60', $codigoRule],
            'numero_serie' => ['nullable', 'string', 'max:100', $serieRule],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'string', 'max:80'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'condicion' => ['required', Rule::in(['NUEVO', 'BUENO', 'REGULAR', 'MALO', 'MALOGRADO'])],
            'estado' => ['required', Rule::in(['DISPONIBLE', 'ASIGNADO', 'MANTENIMIENTO', 'INACTIVO'])],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'numero_serie' => 'número de serie',
            'condicion' => 'condición',
            'observaciones' => 'observaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'unique' => 'Este :attribute ya está registrado.',
            'in' => 'Selecciona un valor válido para :attribute.',
        ];
    }
}
