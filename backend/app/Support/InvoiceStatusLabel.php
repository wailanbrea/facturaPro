<?php

namespace App\Support;

class InvoiceStatusLabel
{
    public static function label(?string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'issued' => 'Emitida',
            'partially_paid' => 'Pago parcial',
            'paid' => 'Pagada',
            'overdue' => 'Vencida',
            'cancelled' => 'Anulada',
            'accepted' => 'Aceptado',
            'converted' => 'Convertido a factura',
            default => self::fallback($status),
        };
    }

    private static function fallback(?string $status): string
    {
        $normalized = trim((string) $status);

        if ($normalized === '') {
            return 'Sin estado';
        }

        return mb_convert_case(str_replace('_', ' ', $normalized), MB_CASE_TITLE, 'UTF-8');
    }
}
