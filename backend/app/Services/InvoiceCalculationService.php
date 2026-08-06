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
     *     discount_total: string,
     *     travel_amount: string,
     *     taxable_base: string,
     *     tax_total: string,
     *     total: string,
     *     amount_received: string,
     *     balance_due: string
     * }
     *
     * @param  string|int|float  $discountPercent  Percentage discount applied over the lines subtotal.
     * @param  string|int|float  $travelAmount  Travel/callout fee added to the taxable base.
     * @param  string|int|float|null  $travelTaxRate  Tax rate for the travel fee; defaults to the
     *                                                highest rate present in the lines.
     */
    public function calculate(
        array $items,
        string|int|float $amountReceived = '0',
        bool $pricesIncludeTax = false,
        int $scale = 4,
        string|int|float $discountPercent = '0',
        string|int|float $travelAmount = '0',
        string|int|float|null $travelTaxRate = null,
    ): array {
        if ($items === []) {
            throw new InvalidArgumentException('Invoice must contain at least one item.');
        }

        $subtotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $total = BigDecimal::zero();

        $calculatedItems = [];

        // Taxable base grouped by tax rate. Needed to prorate the discount and to
        // place the travel fee, so mixed-rate documents still add up.
        $buckets = [];

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

            // Scaled so the key matches the one travelBucketKey() builds.
            $bucketKey = $this->scale($taxRate, $scale);
            $buckets[$bucketKey] ??= ['rate' => $rate, 'base' => BigDecimal::zero()];
            $buckets[$bucketKey]['base'] = $buckets[$bucketKey]['base']->plus($lineSubtotal);

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

        $discountPercentDecimal = $this->optionalDecimal($discountPercent, 'discount_percent');
        $travelAmountDecimal = $this->optionalDecimal($travelAmount, 'travel_amount');

        if ($discountPercentDecimal->isLessThan(0) || $discountPercentDecimal->isGreaterThan(100)) {
            throw new InvalidArgumentException('discount_percent must be between 0 and 100.');
        }

        if ($travelAmountDecimal->isLessThan(0)) {
            throw new InvalidArgumentException('travel_amount must be greater than or equal to zero.');
        }

        $discountTotal = BigDecimal::zero();
        $taxableBase = $subtotal;

        // Without a discount or a travel fee the document behaves exactly as it
        // did before these parameters existed. Short-circuiting keeps every
        // previously issued invoice (and the mobile app, which never sends them)
        // byte-identical.
        if (! $discountPercentDecimal->isZero() || ! $travelAmountDecimal->isZero()) {
            $discountTotal = $this->scaleDecimal(
                $subtotal->multipliedBy($discountPercentDecimal)->dividedBy('100', $scale + 4, RoundingMode::HALF_UP),
                $scale,
            );

            $taxableBase = $subtotal->minus($discountTotal)->plus($travelAmountDecimal);

            [$taxTotal, $total] = $this->recalculateTaxes(
                $buckets,
                $subtotal,
                $discountTotal,
                $travelAmountDecimal,
                $travelTaxRate,
                $taxableBase,
                $scale,
            );
        }

        $amountReceivedDecimal = $this->decimal($amountReceived, 'amount_received');

        if ($amountReceivedDecimal->isLessThan(0)) {
            throw new InvalidArgumentException('amount_received must be greater than or equal to zero.');
        }

        $balanceDue = $total->minus($amountReceivedDecimal);

        return [
            'items' => $calculatedItems,
            'subtotal' => $this->scale($subtotal, $scale),
            'discount_total' => $this->scale($discountTotal, $scale),
            'travel_amount' => $this->scale($travelAmountDecimal, $scale),
            'taxable_base' => $this->scale($taxableBase, $scale),
            'tax_total' => $this->scale($taxTotal, $scale),
            'total' => $this->scale($total, $scale),
            'amount_received' => $this->scale($amountReceivedDecimal, $scale),
            'balance_due' => $this->scale($balanceDue, $scale),
        ];
    }

    /**
     * Recompute tax once the discount and the travel fee move the taxable base.
     *
     * The discount is prorated across each tax-rate bucket by its share of the
     * lines subtotal, so a document mixing 21% and 10% keeps both rates honest.
     * The travel fee lands entirely in one bucket (the highest rate present
     * unless the caller says otherwise).
     *
     * @param  array<string, array{rate: BigDecimal, base: BigDecimal}>  $buckets
     * @return array{0: BigDecimal, 1: BigDecimal} Tax total and grand total.
     */
    private function recalculateTaxes(
        array $buckets,
        BigDecimal $subtotal,
        BigDecimal $discountTotal,
        BigDecimal $travelAmount,
        string|int|float|null $travelTaxRate,
        BigDecimal $taxableBase,
        int $scale,
    ): array {
        $travelBucketKey = $this->travelBucketKey($buckets, $travelTaxRate, $scale);

        $taxTotal = BigDecimal::zero();

        foreach ($buckets as $key => $bucket) {
            $bucketBase = $bucket['base'];

            if (! $discountTotal->isZero() && ! $subtotal->isZero()) {
                $share = $bucketBase->dividedBy($subtotal, $scale + 6, RoundingMode::HALF_UP);
                $bucketBase = $bucketBase->minus($discountTotal->multipliedBy($share));
            }

            if ($key === $travelBucketKey) {
                $bucketBase = $bucketBase->plus($travelAmount);
            }

            $taxTotal = $taxTotal->plus($bucketBase->multipliedBy($bucket['rate']));
        }

        $taxTotal = $this->scaleDecimal($taxTotal, $scale);

        return [$taxTotal, $taxableBase->plus($taxTotal)];
    }

    /**
     * Bucket the travel fee belongs to: the requested rate when it already
     * exists, otherwise the highest rate in the document.
     *
     * @param  array<string, array{rate: BigDecimal, base: BigDecimal}>  $buckets
     */
    private function travelBucketKey(array $buckets, string|int|float|null $travelTaxRate, int $scale): ?string
    {
        if ($buckets === []) {
            return null;
        }

        if ($travelTaxRate !== null && is_numeric($travelTaxRate)) {
            $requested = (string) BigDecimal::of((string) $travelTaxRate)->toScale($scale, RoundingMode::HALF_UP);

            if (array_key_exists($requested, $buckets)) {
                return $requested;
            }
        }

        $highestKey = null;
        $highestRate = null;

        foreach ($buckets as $key => $bucket) {
            if ($highestRate === null || $bucket['rate']->isGreaterThan($highestRate)) {
                $highestRate = $bucket['rate'];
                $highestKey = $key;
            }
        }

        return $highestKey;
    }

    private function optionalDecimal(string|int|float|null $value, string $field): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("$field must be numeric.");
        }

        return BigDecimal::of((string) $value);
    }

    private function scaleDecimal(BigDecimal $value, int $scale): BigDecimal
    {
        return $value->toScale($scale, RoundingMode::HALF_UP);
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
