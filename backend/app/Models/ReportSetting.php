<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSetting extends Model
{
    protected $fillable = [
        'section_1_default_title',
        'section_2_default_title',
        'section_3_default_title',
        'section_4_default_title',
        'intro_text',
        'final_text',
        'report_prefix',
        'next_report_number',
        'number_length',
        'allow_manual_number',
    ];

    protected function casts(): array
    {
        return [
            'next_report_number' => 'integer',
            'number_length' => 'integer',
            'allow_manual_number' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'section_1_default_title' => 'Diagnóstico de la Avería',
            'section_2_default_title' => 'Acciones Realizadas',
            'section_3_default_title' => 'Análisis de Combustión Resultados Post-Reparación',
            'section_4_default_title' => 'Conclusión Técnica',
            'intro_text' => null,
            'final_text' => null,
            'report_prefix' => 'INF-',
            'next_report_number' => 1,
            'number_length' => 6,
            'allow_manual_number' => false,
        ];
    }

    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create(self::defaults());
    }

    public function previewNextNumber(): string
    {
        return $this->report_prefix.str_pad((string) $this->next_report_number, $this->number_length, '0', STR_PAD_LEFT);
    }
}
