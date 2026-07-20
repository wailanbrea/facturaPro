<?php

namespace App\Http\Resources\Api;

use App\Services\ReportNumberService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_1_default_title' => $this->section_1_default_title,
            'section_2_default_title' => $this->section_2_default_title,
            'section_3_default_title' => $this->section_3_default_title,
            'section_4_default_title' => $this->section_4_default_title,
            'intro_text' => $this->intro_text,
            'final_text' => $this->final_text,
            'report_prefix' => $this->report_prefix,
            'next_report_number' => $this->next_report_number,
            'number_length' => $this->number_length,
            'allow_manual_number' => $this->allow_manual_number,
            'next_number_preview' => $request->integer('fiscal_profile_id') > 0
                ? app(ReportNumberService::class)->preview($request->integer('fiscal_profile_id'))
                : $this->previewNextNumber(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
