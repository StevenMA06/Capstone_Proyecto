<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('view-office') ?? false)) {
            return false;
        }
        if ($this->routeIs('contracts.update')) {
            return DB::table('contratos')->where('contrato_id', $this->route('contract'))
                ->where('estado', '!=', 'CANCELADO')->exists();
        }

        return true;
    }

    public function rules(): array
    {
        $rules = [
            'plan_id' => ['required', 'integer', Rule::exists('planes_servicio', 'plan_id')],
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'cliente_id')],
            'zona_id' => ['required', 'integer', Rule::exists('zonas_cobertura', 'zona_id')],
            'responsable_id' => $this->user()?->isManager()
                ? ['required', 'integer', Rule::exists('empleados', 'empleado_id')]
                : ['prohibited'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'duracion_meses' => ['required', 'integer', 'between:1,65535'],
            'observaciones' => ['nullable', 'string', 'max:10000'],
            'documento_pdf' => $this->routeIs('contracts.store') ? ['prohibited'] : [
                'bail', 'nullable', 'file',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value instanceof UploadedFile && $value->getSize() === 0) {
                        $fail('El archivo seleccionado está vacío (0 bytes). Vuelve a exportar el PDF firmado e inténtalo de nuevo.');
                    }
                },
                'mimes:pdf', 'max:10240',
            ],
        ];

        if ($this->routeIs('contracts.update')) {
            $rules['estado'] = ['prohibited'];
            $rules['motivo_cancelacion'] = ['prohibited'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['plan_id' => 'plan', 'cliente_id' => 'cliente', 'zona_id' => 'zona', 'responsable_id' => 'responsable', 'fecha_inicio' => 'fecha de inicio', 'duracion_meses' => 'duración en meses', 'motivo_cancelacion' => 'motivo de cancelación'];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.', 'exists' => 'Selecciona un :attribute válido.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'date_format' => 'Ingresa una fecha válida (año-mes-día).', 'between' => 'La duración debe estar entre 1 y 65535 meses.',
            'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'prohibited' => 'El contrato firmado se adjunta después de registrar el contrato.',
            'responsable_id.prohibited' => 'El responsable se asigna automáticamente desde tu cuenta. Solo Gerencia o Administración puede cambiarlo.',
            'mimes' => 'El archivo seleccionado debe ser un PDF válido.',
            'estado.prohibited' => 'El estado no se cambia desde el formulario de edición.',
            'motivo_cancelacion.prohibited' => 'La cancelación debe tramitarse mediante una solicitud documentada.',
        ];
    }
}
