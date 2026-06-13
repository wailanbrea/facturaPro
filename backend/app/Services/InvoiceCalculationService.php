<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class InvoiceCalculationService
{
    /**
     * @param array<int, array{
     *     description?: string,
     *     quantity: numeric-string|int|float,
     *     unit_cost: numeric-string|int|float,
     *     tax_rate?: numeric-string|int|float|null,
     *     tax_name?: string|null,
     *     tax_id?: int|null
     * }> $items
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     subtotal: string,
     *     tax_total: string,
     *     total: string,
     *     amount_received: string,
     *     balance_due: string
     * }
     */
    public function calculate(
        array $items,
        string|int|float $amountReceived = '0',
        bool $pricesIncludeTax = false,
        int $scale = 4,
    ): array {
        if ($items === []) {
            throw new InvalidArgumentException('Invoice must contain at least one item.');
        }

        $subtotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $total = BigDecimal::zero();

        $calculatedItems = [];

        foreach ($items as $index => $item) {
            $quantity = $this->decimal($item['quantity'] ?? null, "items.$index.quantity");
            $unitCost = $this->decimal($item['unit_cost'] ?? null, "items.$index.unit_cost");
            $taxRate = $this->decimal($item['tax_rate'] ?? '0', "items.$index.tax_rate");

            if ($quantity->isLessThanOrEqualTo(0)) {
                throw new InvalidArgumentException("items.$index.quantity must be greater than zero.");
            }

            if ($unitCost->isLessThan(0)) {
                throw new InvalidArgumentException("items.$index.unit_cost must be greater than or equal to zero.");
            }

            if ($taxRate->isLessThan(0)) {
                throw new InvalidArgumentException("items.$index.tax_rate must be greater than or equal to zero.");
            }

            $rate = $taxRate->dividedBy('100', $scale + 4, RoundingMode::HALF_UP);

            if ($pricesIncludeTax) {
                $lineTotal = $quantity->multipliedBy($unitCost);
                $divisor = BigDecimal::one()->plus($rate);
                $lineSubtotal = $lineTotal->dividedBy($divisor, $scale, RoundingMode::HALF_UP);
                $taxAmount = $lineTotal->minus($lineSubtotal);
                $unitPrice = $unitCost;
            } else {
                $lineSubtotal = $quantity->multipliedBy($unitCost);
                $taxAmount = $lineSubtotal->multipliedBy($rate);
                $unitPrice = $unitCost->plus($unitCost->multipliedBy($rate));
                $lineTotal = $lineSubtotal->plus($taxAmount);
            }

            $lineSubtotal = $this->scale($lineSubtotal, $scale);
            $taxAmount = $this->scale($taxAmount, $scale);
            $unitPrice = $this->scale($unitPrice, $scale);
            $lineTotal = $this->scale($lineTotal, $scale);

            $subtotal = $subtotal->plus($lineSubtotal);
            $taxTotal = $taxTotal->plus($taxAmount);
            $total = $total->plus($lineTotal);

            $calculatedItems[] = [
                'description' => $item['description'] ?? null,
                'quantity' => $this->scale($quantity, $scale),
                'unit_cost' => $this->scale($unitCost, $scale),
                'tax_id' => $item['tax_id'] ?? null,
                'tax_name' => $item['tax_name'] ?? null,
                'tax_rate' => $this->scale($taxRate, $scale),
                'tax_amount' => $taxAmount,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
            ];
        }

        $amountReceivedDecimal = $this->decimal($amountReceived, 'amount_received');

        if ($amountReceivedDecimal->isLessThan(0)) {
            throw new InvalidArgumentException('amount_received must be greater than or equal to zero.');
        }

        $balanceDue = $total->minus($amountReceivedDecimal);

        return [
            'items' => $calculatedItems,
            'subtotal' => $this->scale($subtotal, $scale),
            'tax_total' => $this->scale($taxTotal, $scale),
            'total' => $this->scale($total, $scale),
            'amount_received' => $this->scale($amountReceivedDecimal, $scale),
            'balance_due' => $this->scale($balanceDue, $scale),
        ];
    }

    private function decimal(mixed $value, string $field): BigDecimal
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("$field is required.");
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("$field must be numeric.");
        }

        return BigDecimal::of((string) $value);
    }

    private function scale(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HALF_UP);
    }
}
