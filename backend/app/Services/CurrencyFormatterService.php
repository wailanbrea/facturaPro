<?php

namespace App\Services;

use App\Models\Currency;

class CurrencyFormatterService
{
    /**
     * @param Currency|array{
     *     symbol: string,
     *     decimal_separator: string,
     *     thousand_separator: string,
     *     decimal_places: int,
     *     symbol_position: string
     * } $currency
     */
    public function format(string|int|float $amount, Currency|array $currency): string
    {
        $symbol = $this->value($currency, 'symbol');
        $decimalSeparator = $this->value($currency, 'decimal_separator');
        $thousandSeparator = $this->value($currency, 'thousand_separator');
        $decimalPlaces = (int) $this->value($currency, 'decimal_places');
        $symbolPosition = $this->value($currency, 'symbol_position');

        $formatted = number_format(
            (float) $amount,
            $decimalPlaces,
            $decimalSeparator,
            $thousandSeparator,
        );

        return $symbolPosition === 'after'
            ? $formatted . ' ' . $symbol
            : $symbol . ' ' . $formatted;
    }

    private function value(Currency|array $currency, string $key): mixed
    {
        return $currency instanceof Currency ? $currency->getAttribute($key) : $currency[$key];
    }
}
