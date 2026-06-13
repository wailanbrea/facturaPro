<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'tax_id' => $this->tax_id,
            'tax_name' => $this->tax_name,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'unit_price' => $this->unit_price,
            'line_subtotal' => $this->line_subtotal,
            'line_total' => $this->line_total,
            'sort_order' => $this->sort_order,
        ];
    }
}
