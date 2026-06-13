<?php

namespace App\Services;

use App\Models\Invoice;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class InvoiceStatusService
{
    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const PAID = 'paid';

    public const PARTIALLY_PAID = 'partially_paid';

    public const CANCELLED = 'cancelled';

    public const OVERDUE = 'overdue';

    public const ACCEPTED = 'accepted';

    public const CONVERTED = 'converted';

    /**
     * @var array<int, string>
     */
    public const PAYMENT_STATUSES = [
        self::PAID,
        self::PARTIALLY_PAID,
        self::OVERDUE,
    ];

    public function determine(
        string $currentStatus,
        string|int|float $total,
        string|int|float $amountReceived,
        string|int|float|null $balanceDue = null,
        CarbonInterface|string|null $dueDate = null,
        ?CarbonInterface $today = null,
    ): string {
        if ($currentStatus === self::CANCELLED) {
            return self::CANCELLED;
        }

        if ($currentStatus === self::DRAFT) {
            return self::DRAFT;
        }

        $totalDecimal = BigDecimal::of((string) $total);
        $receivedDecimal = BigDecimal::of((string) $amountReceived);
        $balanceDecimal = $balanceDue === null
            ? $totalDecimal->minus($receivedDecimal)
            : BigDecimal::of((string) $balanceDue);

        if ($totalDecimal->isZero() || $receivedDecimal->isGreaterThanOrEqualTo($totalDecimal)) {
            return self::PAID;
        }

        $today = $today instanceof CarbonInterface
            ? CarbonImmutable::instance($today)
            : CarbonImmutable::today();

        if ($dueDate !== null) {
            $due = $dueDate instanceof CarbonInterface
                ? CarbonImmutable::instance($dueDate)
                : CarbonImmutable::parse($dueDate);

            if ($due->isBefore($today) && $balanceDecimal->isGreaterThan(0)) {
                return self::OVERDUE;
            }
        }

        if ($receivedDecimal->isGreaterThan(0)) {
            return self::PARTIALLY_PAID;
        }

        return self::ISSUED;
    }

    public function statusWhenIssued(Invoice $invoice): string
    {
        if ($invoice->isQuotation()) {
            return self::ISSUED;
        }

        return $this->determine(
            self::ISSUED,
            $invoice->total,
            $invoice->amount_received,
            $invoice->balance_due,
            $invoice->due_date,
        );
    }

    public function statusAfterAmountsChanged(Invoice $invoice, ?string $currentStatus = null): string
    {
        $status = $currentStatus ?? $invoice->status;

        if ($invoice->isQuotation()) {
            return $this->quotationStatus($status);
        }

        return $this->determine(
            $status,
            $invoice->total,
            $invoice->amount_received,
            $invoice->balance_due,
            $invoice->due_date,
        );
    }

    public function quotationStatus(string $status): string
    {
        return match ($status) {
            self::CANCELLED => self::CANCELLED,
            self::DRAFT => self::DRAFT,
            self::ACCEPTED => self::ACCEPTED,
            self::CONVERTED => self::CONVERTED,
            default => self::ISSUED,
        };
    }

    public function canReceivePayments(Invoice $invoice): bool
    {
        return $invoice->isInvoiceDocument();
    }
}
