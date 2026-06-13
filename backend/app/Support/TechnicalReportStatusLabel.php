<?php

namespace App\Support;

class TechnicalReportStatusLabel
{
    public static function label(?string $status): string
    {
        return match ($status) {
            'issued' => 'Emitido',
            'cancelled' => 'Anulado',
            default => 'Borrador',
        };
    }

    public static function tone(?string $status): string
    {
        return match ($status) {
            'issued' => 'success',
            'cancelled' => 'danger',
            default => 'muted',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'draft' => 'Borrador',
            'issued' => 'Emitido',
            'cancelled' => 'Anulado',
        ];
    }
}
