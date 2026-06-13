<?php

namespace Tests\Unit\Services;

use App\Services\CurrencyFormatterService;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterServiceTest extends TestCase
{
    public function test_it_formats_symbol_before_amount(): void
    {
        $formatted = (new CurrencyFormatterService())->format('9440', [
            'symbol' => 'RD$',
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'decimal_places' => 2,
            'symbol_position' => 'before',
        ]);

        $this->assertSame('RD$ 9,440.00', $formatted);
    }

    public function test_it_formats_symbol_after_amount(): void
    {
        $formatted = (new CurrencyFormatterService())->format('9440', [
            'symbol' => 'EUR',
            'decimal_separator' => ',',
            'thousand_separator' => '.',
            'decimal_places' => 2,
            'symbol_position' => 'after',
        ]);

        $this->assertSame('9.440,00 EUR', $formatted);
    }
}
