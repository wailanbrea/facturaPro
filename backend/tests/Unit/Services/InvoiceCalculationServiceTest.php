<?php

namespace Tests\Unit\Services;

use App\Services\InvoiceCalculationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceCalculationServiceTest extends TestCase
{
    #[DataProvider('supportedTaxRatesProvider')]
    public function test_it_calculates_supported_tax_rates(
        string $taxRate,
        string $expectedTaxTotal,
        string $expectedGrandTotal,
    ): void {
        $result = (new InvoiceCalculationService())->calculate([
            ['description' => 'Servicio', 'quantity' => '2', 'unit_cost' => '100', 'tax_rate' => $taxRate],
        ]);

        $this->assertSame('200.0000', $result['subtotal']);
        $this->assertSame($expectedTaxTotal, $result['tax_total']);
        $this->assertSame($expectedGrandTotal, $result['total']);
        $this->assertSame($expectedGrandTotal, $result['balance_due']);
    }

    public function test_it_calculates_lines_when_prices_do_not_include_tax(): void
    {
        $result = (new InvoiceCalculationService())->calculate([
            ['description' => 'Servicio', 'quantity' => '2', 'unit_cost' => '100', 'tax_rate' => '18'],
        ]);

        $this->assertSame('200.0000', $result['subtotal']);
        $this->assertSame('36.0000', $result['tax_total']);
        $this->assertSame('236.0000', $result['total']);
        $this->assertSame('236.0000', $result['balance_due']);
        $this->assertSame('118.0000', $result['items'][0]['unit_price']);
        $this->assertSame('236.0000', $result['items'][0]['line_total']);
    }

    public function test_it_calculates_lines_when_prices_include_tax(): void
    {
        $result = (new InvoiceCalculationService())->calculate(
            [['description' => 'Servicio', 'quantity' => '2', 'unit_cost' => '118', 'tax_rate' => '18']],
            pricesIncludeTax: true,
        );

        $this->assertSame('200.0000', $result['subtotal']);
        $this->assertSame('36.0000', $result['tax_total']);
        $this->assertSame('236.0000', $result['total']);
        $this->assertSame('118.0000', $result['items'][0]['unit_price']);
    }

    public function test_it_supports_tax_exempt_lines_and_amount_received(): void
    {
        $result = (new InvoiceCalculationService())->calculate([
            ['description' => 'Exento', 'quantity' => '3', 'unit_cost' => '50', 'tax_rate' => '0'],
        ], amountReceived: '25');

        $this->assertSame('150.0000', $result['subtotal']);
        $this->assertSame('0.0000', $result['tax_total']);
        $this->assertSame('150.0000', $result['total']);
        $this->assertSame('125.0000', $result['balance_due']);
    }

    public function test_zero_discount_and_travel_leave_the_legacy_totals_untouched(): void
    {
        $items = [
            ['description' => 'Servicio', 'quantity' => '2', 'unit_cost' => '100', 'tax_rate' => '21'],
        ];

        $legacy = (new InvoiceCalculationService())->calculate($items);
        $explicit = (new InvoiceCalculationService())->calculate(
            $items,
            discountPercent: '0',
            travelAmount: '0',
        );

        // The mobile app never sends the new fields; its totals must not move.
        $this->assertSame($legacy['subtotal'], $explicit['subtotal']);
        $this->assertSame($legacy['tax_total'], $explicit['tax_total']);
        $this->assertSame($legacy['total'], $explicit['total']);
        $this->assertSame('242.0000', $explicit['total']);
        $this->assertSame('0.0000', $explicit['discount_total']);
        $this->assertSame('200.0000', $explicit['taxable_base']);
    }

    public function test_discount_reduces_the_taxable_base_and_the_tax(): void
    {
        $result = (new InvoiceCalculationService())->calculate(
            [['description' => 'Servicio', 'quantity' => '1', 'unit_cost' => '1000', 'tax_rate' => '21']],
            discountPercent: '10',
        );

        $this->assertSame('1000.0000', $result['subtotal']);
        $this->assertSame('100.0000', $result['discount_total']);
        $this->assertSame('900.0000', $result['taxable_base']);
        $this->assertSame('189.0000', $result['tax_total']);
        $this->assertSame('1089.0000', $result['total']);
    }

    public function test_travel_fee_is_added_to_the_base_and_taxed(): void
    {
        $result = (new InvoiceCalculationService())->calculate(
            [['description' => 'Servicio', 'quantity' => '1', 'unit_cost' => '1000', 'tax_rate' => '21']],
            travelAmount: '40',
        );

        $this->assertSame('40.0000', $result['travel_amount']);
        $this->assertSame('1040.0000', $result['taxable_base']);
        $this->assertSame('218.4000', $result['tax_total']);
        $this->assertSame('1258.4000', $result['total']);
    }

    public function test_discount_is_prorated_across_mixed_tax_rates(): void
    {
        $result = (new InvoiceCalculationService())->calculate([
            ['description' => 'Al 21', 'quantity' => '1', 'unit_cost' => '600', 'tax_rate' => '21'],
            ['description' => 'Al 10', 'quantity' => '1', 'unit_cost' => '400', 'tax_rate' => '10'],
        ], discountPercent: '20');

        // 1000 - 20% = 800 taxable: 480 @21% = 100.80, 320 @10% = 32.00
        $this->assertSame('1000.0000', $result['subtotal']);
        $this->assertSame('200.0000', $result['discount_total']);
        $this->assertSame('800.0000', $result['taxable_base']);
        $this->assertSame('132.8000', $result['tax_total']);
        $this->assertSame('932.8000', $result['total']);
    }

    public function test_travel_fee_lands_on_the_highest_rate_present(): void
    {
        $result = (new InvoiceCalculationService())->calculate([
            ['description' => 'Al 21', 'quantity' => '1', 'unit_cost' => '100', 'tax_rate' => '21'],
            ['description' => 'Al 10', 'quantity' => '1', 'unit_cost' => '100', 'tax_rate' => '10'],
        ], travelAmount: '50');

        // 150 @21% = 31.50, 100 @10% = 10.00
        $this->assertSame('250.0000', $result['taxable_base']);
        $this->assertSame('41.5000', $result['tax_total']);
        $this->assertSame('291.5000', $result['total']);
    }

    public function test_it_rejects_a_discount_outside_zero_to_one_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InvoiceCalculationService())->calculate(
            [['description' => 'Servicio', 'quantity' => '1', 'unit_cost' => '100', 'tax_rate' => '21']],
            discountPercent: '120',
        );
    }

    public function test_it_rejects_empty_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InvoiceCalculationService())->calculate([]);
    }

    public function test_it_rejects_invalid_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InvoiceCalculationService())->calculate([
            ['description' => 'Servicio', 'quantity' => '0', 'unit_cost' => '100', 'tax_rate' => '18'],
        ]);
    }

    /**
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function supportedTaxRatesProvider(): array
    {
        return [
            'iva_21' => ['21', '42.0000', '242.0000'],
            'itbis_18' => ['18', '36.0000', '236.0000'],
            'tax_7' => ['7', '14.0000', '214.0000'],
            'exento_0' => ['0', '0.0000', '200.0000'],
        ];
    }
}
