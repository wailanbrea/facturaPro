<?php

namespace App\Http\Requests\Api;

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
            'client_id' => ['required', 'exists:clients,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'fiscal_profile_id' => ['required', 'exists:fiscal_profiles,id', Rule::in($this->user()->availableFiscalProfileIds())],
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
            if ($this->input('document_type', 'invoice') !== 'quotation') {
                return;
            }

            if ((float) $this->input('amount_received', 0) > 0.0) {
                $validator->errors()->add('amount_received', 'Los presupuestos no aceptan importes recibidos.');
            }
        });
    }
}
