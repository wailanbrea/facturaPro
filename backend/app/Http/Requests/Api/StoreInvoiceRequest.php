<?php

namespace App\Http\Requests\Api;

use App\Models\FiscalProfileLogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
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
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:invoices,invoice_number'],
            'document_type' => ['nullable', Rule::in(['invoice', 'quotation'])],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_term_id' => ['required', 'exists:payment_terms,id'],
            'client_id' => ['nullable', 'exists:clients,id', 'required_without:client_name'],
            'client_name' => ['nullable', 'string', 'max:255', 'required_without:client_id'],
            'client_tax_id' => ['nullable', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_city' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'fiscal_profile_id' => ['required', 'exists:fiscal_profiles,id', Rule::in($this->user()->availableFiscalProfileIds())],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'warranty_id' => ['required', 'exists:warranties,id'],
            'warranty_text' => ['nullable', 'string'],
            'legal_text' => ['nullable', 'string'],
            'conformity_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_id' => ['required', 'exists:taxes,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('document_type', 'invoice') === 'quotation' && (float) $this->input('amount_received', 0) > 0.0) {
                $validator->errors()->add('amount_received', 'Los presupuestos no aceptan importes recibidos.');
            }

            if (blank($this->input('logo_path'))) {
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
