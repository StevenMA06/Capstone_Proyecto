<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $material = $this->route('material');

        $codigoRule = Rule::unique('materiales', 'codigo');

        if ($material instanceof Material) {
            $codigoRule->ignore($material);
        }

        return [
            'codigo' => ['nullable', 'string', 'max:60', $codigoRule],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'unidad_base' => ['required', 'string', 'max:30'],
            'unidad_empaque' => ['nullable', 'required_with:factor_conversion', 'string', 'max:30'],
            'factor_conversion' => ['nullable', 'required_with:unidad_empaque', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999.999'],
            'stock_actual' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'stock_minimo' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'unidad_base' => 'unidad base',
            'unidad_empaque' => 'unidad de empaque',
            'factor_conversion' => 'factor de conversión',
            'stock_actual' => 'stock actual',
            'stock_minimo' => 'stock mínimo',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_with' => 'El campo :attribute es obligatorio cuando se especifica :values.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'unique' => 'Este :attribute ya está registrado.',
            'decimal' => 'El campo :attribute admite hasta 3 decimales.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute debe ser mayor o igual a :min.',
            'max' => 'El campo :attribute no puede exceder :max.',
            'in' => 'Selecciona un valor válido para :attribute.',
        ];
    }
}
