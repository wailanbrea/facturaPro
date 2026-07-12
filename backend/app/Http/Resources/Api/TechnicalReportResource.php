<?php

namespace App\Http\Resources\Api;

use App\Support\TechnicalReportStatusLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicalReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'report_date' => $this->report_date?->toDateString(),
            'fiscal_profile_id' => $this->fiscal_profile_id,
            'seller_name' => $this->seller_name,
            'seller_tax_id' => $this->seller_tax_id,
            'seller_address' => $this->seller_address,
            'seller_city' => $this->seller_city,
            'seller_logo_path' => $this->seller_logo_path,
            'client_id' => $this->client_id,
            'recipient_name' => $this->recipient_name,
            'recipient_tax_id' => $this->recipient_tax_id,
            'recipient_address' => $this->recipient_address,
            'section_1_title' => $this->section_1_title,
            'section_1_content' => $this->section_1_content,
            'section_2_title' => $this->section_2_title,
            'section_2_content' => $this->section_2_content,
            'section_3_title' => $this->section_3_title,
            'section_3_content' => $this->section_3_content,
            'section_4_title' => $this->section_4_title,
            'section_4_content' => $this->section_4_content,
            'intro_text' => $this->intro_text,
            'final_text' => $this->final_text,
            'notes' => $this->notes,
            'status' => $this->status,
            'status_label' => TechnicalReportStatusLabel::label($this->status),
            'pdf_path' => $this->pdf_path,
            'verification_code' => $this->verification_code,
            'signed_at' => $this->signed_at?->toISOString(),
            'pdf_sha256' => $this->pdf_sha256,
            'created_by_id' => $this->created_by,
            'updated_by_id' => $this->updated_by,
            'created_by' => $this->createdBy?->name,
            'updated_by' => $this->updatedBy?->name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
