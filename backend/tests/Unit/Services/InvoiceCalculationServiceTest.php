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
