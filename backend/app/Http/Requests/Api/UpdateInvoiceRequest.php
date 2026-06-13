<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends FormRequest
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
        $invoiceId = $this->route('invoice')?->getKey();

        return [
            'invoice_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('invoices', 'invoice_number')->ignore($invoiceId),
            ],
            'document_type' => ['sometimes', 'nullable', Rule::in(['invoice', 'quotation'])],
            'invoice_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_term_id' => ['sometimes', 'required', 'exists:payment_terms,id'],
            'client_id' => ['sometimes', 'required', 'exists:clients,id'],
            'currency_id' => ['sometimes', 'required', 'exists:currencies,id'],
            'fiscal_profile_id' => ['sometimes', 'required', 'exists:fiscal_profiles,id', Rule::in($this->user()->availableFiscalProfileIds())],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'warranty_id' => ['sometimes', 'required', 'exists:warranties,id'],
            'warranty_text' => ['nullable', 'string'],
            'legal_text' => ['nullable', 'string'],
            'conformity_text' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_id' => ['required_with:items', 'exists:taxes,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $documentType = $this->input('document_type', $this->route('invoice')?->document_type ?? 'invoice');

            if ($documentType !== 'quotation') {
                return;
            }

            if ($this->has('amount_received') && (float) $this->input('amount_received', 0) > 0.0) {
                $validator->errors()->add('amount_received', 'Los presupuestos no aceptan importes recibidos.');
            }
        });
    }
}
