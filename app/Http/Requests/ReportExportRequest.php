<?php

namespace App\Http\Requests;

use App\Services\ReportExportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('reports.view')) {
            return false;
        }

        return $this->user()->isManager() || in_array($this->input('type'), ReportExportService::OFFICE_TYPES, true);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(ReportExportService::DEFINITIONS))],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.'];
    }
}

