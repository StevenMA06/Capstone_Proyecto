<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment=$this->route('payment');
        if ($this->boolean('marcar_realizado') && !$this->user()?->hasPermission('payments.complete')) return false;
        if ($this->filled('estado') && $this->input('estado') !== $payment?->estado) return false;
        return $this->user()?->can('manage-payments') ?? false;
    }

    public function rules(): array
    {
        $payment = $this->route('payment');

        return [
            // Contrato (opcional en edición, pero si se proporciona debe existir)
            'contrato_id' => ['sometimes', 'required', 'integer', 'exists:contratos,contrato_id'],
            
            // Código no se puede cambiar en edición
            'codigo' => ['sometimes', 'string', 'max:40', Rule::unique('pagos', 'codigo')->ignore($payment)],
            
            // Periodo de servicio
            'periodo_servicio' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            
            // Fecha de vencimiento
            'fecha_vencimiento' => ['sometimes', 'required', 'date'],
            
            // Fecha de pago (sin restricciones - puede ser antes o después del vencimiento)
            'fecha_pago' => ['nullable', 'date'],
            
            // Monto
            'monto' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:9999999999.99'],
            
            // Método de pago
            'metodo_pago' => ['nullable', 'string', 'max:60'],
            
            // Método de pago personalizado (cuando se selecciona OTRO)
            'metodo_pago_otro' => ['nullable', 'required_if:metodo_pago,OTRO', 'string', 'max:60'],
            
            // Comprobante de pago (archivo subido)
            'comprobante_pago' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            
            // Referencia externa
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            
            // Estado (no se puede cambiar directamente, usar endpoints específicos)
            'estado' => ['sometimes', Rule::in(['PENDIENTE', 'REALIZADO', 'VENCIDO', 'ANULADO'])],
            
            // Constancia
            'constancia_ruta' => ['nullable', 'string', 'max:500'],
            
            // Observaciones
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
            'metodo_pago_otro' => 'método de pago personalizado',
            'comprobante_pago' => 'comprobante de pago',
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
            'before_or_equal' => 'La :attribute no puede ser futura.',
            'periodo_servicio.regex' => 'El periodo de servicio debe tener el formato YYYY-MM (ejemplo: 2026-09).',
            'in' => 'El :attribute seleccionado no es válido.',
            'required_if' => 'El campo :attribute es obligatorio cuando seleccionas "Otro".',
            'comprobante_pago.mimes' => 'El comprobante debe ser un archivo JPG, PNG o PDF.',
            'comprobante_pago.max' => 'El comprobante no puede superar 5MB.',
            'comprobante_pago.file' => 'El comprobante debe ser un archivo válido.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Validar que si hay fecha_pago, debe haber método de pago
            if ($this->filled('fecha_pago') && ! $this->filled('metodo_pago')) {
                $payment = $this->route('payment');
                // Si el pago ya tiene método registrado, no es necesario
                if (! $payment || ! $payment->metodo_pago) {
                    $validator->errors()->add(
                        'metodo_pago',
                        'El método de pago es requerido cuando se registra la fecha de pago.'
                    );
                }
            }

            // No hay restricción de que fecha_pago deba ser posterior al vencimiento
            // El cliente puede pagar antes (adelantado) o después (vencido) del vencimiento
        });
    }
}
