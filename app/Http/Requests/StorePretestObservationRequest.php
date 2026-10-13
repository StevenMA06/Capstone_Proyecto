<?php
namespace App\Http\Requests;
use App\Support\IndicatorDefinitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePretestObservationRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isManager() ?? false; }
    protected function prepareForValidation(): void
    {
        foreach (['inicio','fin'] as $field) {
            $value=$this->input($field);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$value)) {
                $this->merge([$field=>$value.':00']);
            }
        }
    }
    public function rules(): array
    {
        return [
            'token'=>['required','uuid'],
            'indicador'=>['required',Rule::in(array_keys(IndicatorDefinitions::measurable()))],
            'inicio'=>['required','date_format:Y-m-d\TH:i:s','before:fin'],
            'fin'=>['required','date_format:Y-m-d\TH:i:s','before_or_equal:'.now('America/Lima')->format('Y-m-d\TH:i:s')],
            'interrupciones_segundos'=>['required','integer','min:0','max:4294967295'],
            'excepcional'=>['sometimes','boolean'],
            'referencia'=>['required','string','max:120'],
            'observacion'=>['nullable','string','max:2000',Rule::requiredIf(fn()=> $this->boolean('excepcional') || (int)$this->input('interrupciones_segundos',0)>0)],
            'limites_confirmados'=>['accepted'],
        ];
    }
    public function messages(): array
    {
        return ['limites_confirmados.accepted'=>'Confirma que observaste los eventos de inicio y fin definidos.',
            'inicio.before'=>'El inicio debe ser anterior al fin.', 'fin.before_or_equal'=>'No se admiten observaciones con finalización futura.',
            'observacion.required'=>'Describe el caso excepcional o la causa de las interrupciones.'];
    }
}
