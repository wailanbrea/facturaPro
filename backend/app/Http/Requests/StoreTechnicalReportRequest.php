<?php

namespace App\Http\Requests;

use App\Models\FiscalProfileLogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTechnicalReportRequest extends FormRequest
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
            'report_number' => ['nullable', 'string', 'max:255', Rule::unique('technical_reports', 'report_number')],
            'report_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['draft', 'issued', 'cancelled'])],
            'fiscal_profile_id' => ['required', 'exists:fiscal_profiles,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_tax_id' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['required', 'string'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'section_1_title' => ['required', 'string', 'max:255'],
            'section_1_content' => ['nullable', 'string'],
            'section_2_title' => ['nullable', 'string', 'max:255'],
            'section_2_content' => ['nullable', 'string'],
            'section_3_title' => ['nullable', 'string', 'max:255'],
            'section_3_content' => ['nullable', 'string'],
            'section_4_title' => ['nullable', 'string', 'max:255'],
            'section_4_content' => ['nullable', 'string'],
            'intro_text' => ['nullable', 'string'],
            'final_text' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (blank($this->input('logo_path'))) {
                if (FiscalProfileLogo::query()->where('fiscal_profile_id', $this->input('fiscal_profile_id'))->exists()) {
                    $validator->errors()->add('logo_path', 'Debe seleccionar un logo para este perfil fiscal.');
                }

                return;
            }

            $exists = FiscalProfileLogo::query()
                ->where('fiscal_profile_id', $this->input('fiscal_profile_id'))
                ->where('path', $this->input('logo_path'))
                ->exists();

            if (! $exists) {
                $validator->errors()->add('logo_path', 'El logo seleccionado no pertenece a este perfil.');
            }
        });
    }
}
