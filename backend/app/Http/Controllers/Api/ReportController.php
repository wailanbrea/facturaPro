<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\InvoiceStatusLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'currency_code' => ['nullable', 'exists:currencies,code'],
        ]);

        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'currency_code' => $validated['currency_code'] ?? null,
        ];

        $data = $reports->build($filters);

        return response()->json([
            'data' => [
                'filters' => $filters,
                'overview' => [
                    'invoices_count' => (int) $data['overview']->invoices_count,
                    'overdue_count' => (int) $data['overview']->overdue_count,
                ],
                'totals' => $data['totals'] ? $this->moneyRow($data['totals']) : null,
                'totals_by_currency' => $data['totalsByCurrency']->map(fn (object $row): array => $this->moneyRow($row))->values(),
                'by_date' => $data['byDate']->map(fn (object $row): array => [
                    'invoice_day' => (string) $row->invoice_day,
                    ...$this->moneyRow($row),
                ])->values(),
                'by_status' => $data['byStatus']->map(fn (object $row): array => [
                    'status' => (string) $row->status,
                    'status_label' => InvoiceStatusLabel::label((string) $row->status),
                    ...$this->moneyRow($row),
                ])->values(),
                'by_client' => $data['byClient']->map(fn (object $row): array => [
                    'client_name' => (string) $row->client_name,
                    ...$this->moneyRow($row),
                ])->values(),
                'overdue_invoices' => $data['overdueInvoices']->map(fn (object $invoice): array => [
                    'id' => (int) $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'client_name' => (string) $invoice->client_name,
                    'currency_code' => (string) $invoice->currency_code,
                    'currency_symbol' => (string) $invoice->currency_symbol,
                    'total' => $this->decimal($invoice->total),
                    'balance_due' => $this->decimal($invoice->balance_due),
                    'status' => (string) $invoice->status,
                    'status_label' => InvoiceStatusLabel::label((string) $invoice->status),
                ])->values(),
                'can_show_unified_money_totals' => $reports->totalsAvailable($data['totalsByCurrency']),
            ],
        ]);
    }

    /**
     * @return array<string, string|int>
     */
    private function moneyRow(object $row): array
    {
        return [
            'currency_code' => (string) $row->currency_code,
            'currency_symbol' => (string) $row->currency_symbol,
            'invoices_count' => (int) $row->invoices_count,
            'total_invoiced' => $this->decimal($row->total_invoiced),
            'total_collected' => $this->decimal($row->total_collected),
            'total_pending' => $this->decimal($row->total_pending),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
