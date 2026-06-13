<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_1_default_title' => ['required', 'string', 'max:255'],
            'section_2_default_title' => ['required', 'string', 'max:255'],
            'section_3_default_title' => ['required', 'string', 'max:255'],
            'section_4_default_title' => ['required', 'string', 'max:255'],
            'intro_text' => ['nullable', 'string'],
            'final_text' => ['nullable', 'string'],
            'report_prefix' => ['required', 'string', 'max:20'],
            'next_report_number' => ['required', 'integer', 'min:1'],
            'number_length' => ['required', 'integer', 'min:1', 'max:10'],
            'allow_manual_number' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_manual_number' => $this->boolean('allow_manual_number'),
        ]);
    }
}
