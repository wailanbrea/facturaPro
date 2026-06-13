<?php

namespace Tests\Unit\Services;

use App\Services\InvoiceStatusService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class InvoiceStatusServiceTest extends TestCase
{
    private InvoiceStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvoiceStatusService();
    }

    public function test_draft_and_cancelled_statuses_are_preserved(): void
    {
        $this->assertSame('draft', $this->service->determine('draft', '100', '0'));
        $this->assertSame('cancelled', $this->service->determine('cancelled', '100', '100'));
    }

    public function test_it_marks_invoice_as_paid_when_received_covers_total(): void
    {
        $this->assertSame('paid', $this->service->determine('issued', '100', '100'));
        $this->assertSame('paid', $this->service->determine('issued', '100', '150'));
    }

    public function test_it_marks_invoice_as_partially_paid(): void
    {
        $this->assertSame('partially_paid', $this->service->determine('issued', '100', '30'));
    }

    public function test_it_marks_invoice_as_overdue_when_due_date_passed_with_balance(): void
    {
        $this->assertSame(
            'overdue',
            $this->service->determine(
                'issued',
                '100',
                '0',
                '100',
                '2026-05-20',
                CarbonImmutable::parse('2026-05-21'),
            ),
        );
    }

    public function test_it_marks_unpaid_non_overdue_invoice_as_issued(): void
    {
        $this->assertSame(
            'issued',
            $this->service->determine(
                'issued',
                '100',
                '0',
                '100',
                '2026-05-22',
                CarbonImmutable::parse('2026-05-21'),
            ),
        );
    }
}
