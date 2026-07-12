<?php

namespace App\Http\Resources\Api;

use App\Support\InvoiceStatusLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'document_type' => $this->document_type,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'payment_term_id' => $this->payment_term_id,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'client_tax_id' => $this->client_tax_id,
            'client_address' => $this->client_address,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->currency_symbol,
            'currency_decimal_separator' => $this->currency_decimal_separator,
            'currency_thousand_separator' => $this->currency_thousand_separator,
            'currency_decimal_places' => $this->currency_decimal_places,
            'currency_symbol_position' => $this->currency_symbol_position,
            'fiscal_profile_id' => $this->fiscal_profile_id,
            'logo_path' => $this->logo_path,
            'seller_name' => $this->seller_name,
            'seller_tax_id' => $this->seller_tax_id,
            'seller_address' => $this->seller_address,
            'seller_city' => $this->seller_city,
            'bank_account_id' => $this->bank_account_id,
            'warranty_id' => $this->warranty_id,
            'warranty_text' => $this->warranty_text,
            'legal_text' => $this->legal_text,
            'conformity_text' => $this->conformity_text,
            'observations' => $this->observations,
            'amount_received' => $this->amount_received,
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'balance_due' => $this->balance_due,
            'status' => $this->status,
            'status_label' => InvoiceStatusLabel::label($this->status),
            'prepared_by' => $this->prepared_by,
            'received_by' => $this->received_by,
            'pdf_path' => $this->pdf_path,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->whenLoaded('payments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
