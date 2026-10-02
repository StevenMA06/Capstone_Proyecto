<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $material = $this->route('material');
        
        $rules = [
            'observacion' => ['nullable', 'string', 'max:500'],
        ];

        // Si el material usa inventario en empaques, solo permitir enteros
        if ($material && $material->inventario_en_empaque) {
            $rules['cantidad'] = ['required', 'integer', 'min:1', 'max:999999999'];
        } else {
            $rules['cantidad'] = ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'observacion' => 'observación',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'decimal' => 'El campo :attribute admite hasta 3 decimales.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute debe ser mayor a cero.',
            'max' => 'El campo :attribute no puede exceder :max.',
            'string' => 'El campo :attribute debe ser texto.',
        ];
    }
}
