<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\CarbonImmutable;

class AnalyticsQueryRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    protected function prepareForValidation(): void
    {
        $this->merge([
            'desde'=>$this->input('desde',$this->input('fecha_inicio')) ?: ($this->boolean('vista_previa') ? now('America/Lima')->subMonthsNoOverflow(5)->startOfMonth() : now('America/Lima')->startOfMonth())->toDateString(),
            'hasta'=>$this->input('hasta',$this->input('fecha_fin')) ?: now('America/Lima')->toDateString(),
        ]);
    }
    public function rules(): array
    {
        return [
            'vista_previa'=>['sometimes','boolean'],
            'desde'=>['required','date_format:Y-m-d'], 'hasta'=>['required','date_format:Y-m-d','after_or_equal:desde'],
            'proceso'=>['nullable',Rule::in(['all','clientes','contratos','pagos','instalaciones','inventario'])],
            'indicador'=>['nullable',Rule::in(['clientes','contratos','ventas','pagos','instalaciones','equipos','materiales','kardex'])],
            'tipo_grafico'=>['nullable',Rule::in(['line','bar','pie','doughnut','area'])],
            'agrupacion'=>['nullable',Rule::in(['dia','semana','mes','estado','empleado'])],
            'medida'=>['nullable',Rule::in(['cantidad','monto'])],
            'top'=>['nullable','integer','min:1','max:50'],
            'empleado_id'=>['nullable','integer','exists:empleados,empleado_id'],
        ];
    }
    public function withValidator($validator): void
    {
        $validator->after(function($validator): void {
            if ($validator->errors()->has('desde') || $validator->errors()->has('hasta')) return;
            if (CarbonImmutable::parse($this->input('desde'))->diffInMonths(CarbonImmutable::parse($this->input('hasta'))) > 60) {
                $validator->errors()->add('hasta','Selecciona un período de hasta cinco años.');
            }
        });
    }
    public function period(): array { return [$this->validated('desde'),$this->validated('hasta')]; }
}
