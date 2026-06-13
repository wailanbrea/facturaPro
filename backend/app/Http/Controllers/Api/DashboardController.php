<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(): JsonResponse
    {
        $m = $this->dashboard->metrics();

        return response()->json([
            'invoice_count' => $m['invoiceCount'],
            'client_count' => $m['clientCount'],
            'total_billed' => $this->money($m['totalBilled']),
            'total_billed_month' => $this->money($m['totalBilledMonth']),
            'monthly_trend' => $m['monthlyTrend'] === null ? null : round($m['monthlyTrend'], 2),
            'total_collected' => $this->money($m['totalCollected']),
            'collection_rate' => round($m['collectionRate'], 2),
            'pending_balance' => $this->money($m['pendingBalance']),
            'pending_count' => $m['pendingCount'],
            'overdue_count' => $m['overdueCount'],
            'currency_symbol' => $this->dashboardCurrencySymbol(),
            'monthly_series' => array_map(static fn (array $p): array => [
                'label' => $p['label'],
                'value' => number_format($p['value'], 2, '.', ''),
            ], $m['monthlySeries']),
            'status_chart' => $m['statusChart'],
            'recent_invoices' => $m['recentInvoices']->map(static fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client_name,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'status' => $invoice->status,
                'currency_symbol' => $invoice->currency_symbol,
                'total' => (string) $invoice->total,
            ])->values(),
        ]);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Symbol shown alongside the aggregated totals. Uses the default currency so
     * the app and web display the same currency on the summary cards.
     */
    private function dashboardCurrencySymbol(): ?string
    {
        return Invoice::query()
            ->where('document_type', Invoice::DOCUMENT_TYPE_INVOICE)
            ->latest('invoice_date')
            ->value('currency_symbol');
    }
}
