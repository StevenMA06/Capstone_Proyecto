<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-payments') ?? false;
    }

    public function rules(): array
    {
        $payment = $this->route('payment');
        
        // Código único solo para creación (en edición se mantiene)
        $codigoRule = ['nullable', 'string', 'max:40'];
        if (! $payment instanceof Payment) {
            $codigoRule[] = Rule::unique('pagos', 'codigo');
        }

        return [
            // RF-PAG-01, RF-PAG-02: Contrato requerido y debe existir
            'contrato_id' => ['required', 'integer', 'exists:contratos,contrato_id'],
            
            // Código único del pago
            'codigo' => $codigoRule,
            
            // Periodo de servicio (formato YYYY-MM)
            'periodo_servicio' => ['nullable', 'string', 'date_format:Y-m'],
            
            // RF-PAG-03: Fecha de vencimiento requerida (puede ser futura)
            'fecha_vencimiento' => ['required', 'date'],
            
            // Fecha de pago (solo si ya fue realizado) - puede ser hasta la fecha de vencimiento
            'fecha_pago' => ['nullable', 'date'],
            
            // RF-PAG-04: Monto requerido, numérico, mayor que cero
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            
            // Método de pago
            'metodo_pago' => ['nullable', 'string', 'max:60'],
            
            // Referencia externa (número de operación, transacción, etc.)
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            
            // Estado del pago
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'REALIZADO', 'VENCIDO', 'ANULADO'])],
            
            // Ruta de constancia (se genera automáticamente)
            'constancia_ruta' => ['nullable', 'string', 'max:500'],
            
            // Observaciones adicionales
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contrato_id' => 'contrato',
            'codigo' => 'código del pago',
            'periodo_servicio' => 'periodo de servicio',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'fecha_pago' => 'fecha de pago',
            'monto' => 'monto',
            'metodo_pago' => 'método de pago',
            'referencia_externa' => 'referencia externa',
            'estado' => 'estado',
            'constancia_ruta' => 'ruta de constancia',
            'observaciones' => 'observaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'string' => 'El campo :attribute debe ser texto.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'exists' => 'El contrato seleccionado no existe.',
            'unique' => 'Este código de pago ya está registrado.',
            'gt' => 'El :attribute debe ser mayor que cero.',
            'after_or_equal' => 'La :attribute debe ser hoy o una fecha futura.',
            'before_or_equal' => 'La :attribute no puede ser futura.',
            'periodo_servicio.date_format' => 'El periodo de servicio debe tener el formato YYYY-MM (ejemplo: 2026-09).',
            'in' => 'El :attribute seleccionado no es válido.',
        ];
    }

    /**
     * Mensajes personalizados para validación después del request
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Validar que si hay fecha_pago, debe haber método de pago
            if ($this->filled('fecha_pago') && ! $this->filled('metodo_pago')) {
                $validator->errors()->add(
                    'metodo_pago',
                    'El método de pago es requerido cuando se registra la fecha de pago.'
                );
            }

            // Validar que fecha_pago no sea anterior a fecha_vencimiento
            if ($this->filled('fecha_pago') && $this->filled('fecha_vencimiento')) {
                $fechaPago = \Carbon\Carbon::parse($this->input('fecha_pago'));
                $fechaVencimiento = \Carbon\Carbon::parse($this->input('fecha_vencimiento'));
                
                if ($fechaPago->lt($fechaVencimiento)) {
                    $validator->errors()->add(
                        'fecha_pago',
                        'La fecha de pago no puede ser anterior a la fecha de vencimiento.'
                    );
                }
            }
        });
    }
}
