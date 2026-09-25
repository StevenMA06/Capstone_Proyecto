<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nuevo_stock' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'justificacion' => ['required', 'string', 'min:10', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nuevo_stock' => 'nuevo stock',
            'justificacion' => 'justificación',
            'observacion' => 'observación',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'decimal' => 'El campo :attribute admite hasta 3 decimales.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres/valor.',
            'max' => 'El campo :attribute no puede exceder :max.',
            'string' => 'El campo :attribute debe ser texto.',
        ];
    }
}
