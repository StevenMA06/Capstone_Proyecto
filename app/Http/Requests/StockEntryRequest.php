<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
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
            'numeric' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute debe ser mayor a cero.',
            'max' => 'El campo :attribute no puede exceder :max.',
            'string' => 'El campo :attribute debe ser texto.',
        ];
    }
}
