<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Support\InvoiceStatusLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the dashboard metrics shown on both the web panel
 * and the Android app (via GET /api/dashboard). All figures are aggregated
 * server-side over the whole dataset and restricted to actual invoices
 * (document_type = invoice), so quotations never inflate the numbers.
 */
class DashboardService
{
    /**
     * @return array{
     *     invoiceCount: int,
     *     clientCount: int,
     *     totalBilled: float,
     *     totalBilledMonth: float,
     *     monthlyTrend: float|null,
     *     totalCollected: float,
     *     collectionRate: float,
     *     pendingBalance: float,
     *     pendingCount: int,
     *     overdueCount: int,
     *     monthlySeries: array<int, array{label: string, value: float}>,
     *     statusChart: array<int, array{status: string, label: string, count: int}>,
     *     recentInvoices: EloquentCollection<int, Invoice>
     * }
     */
    public function metrics(): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        $previousMonthStart = $monthStart->subMonth();
        $previousMonthEnd = $previousMonthStart->endOfMonth();

        $invoices = fn () => Invoice::query()->where('document_type', Invoice::DOCUMENT_TYPE_INVOICE);

        $totalBilledMonth = (float) $invoices()
            ->whereBetween('invoice_date', [$monthStart, $monthEnd])
            ->sum('total');

        $previousMonthBilled = (float) $invoices()
            ->whereBetween('invoice_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('total');

        $monthlyTrend = $previousMonthBilled > 0
            ? (($totalBilledMonth - $previousMonthBilled) / $previousMonthBilled) * 100
            : null;

        $totalCollected = (float) $invoices()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->selectRaw('SUM(total - balance_due) as collected')
            ->value('collected');

        $totalBilledAll = (float) $invoices()->sum('total');

        $collectionRate = $totalBilledAll > 0
            ? min(100, ($totalCollected / $totalBilledAll) * 100)
            : 0.0;

        $pendingBalance = (float) $invoices()
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->sum('balance_due');

        $pendingCount = (int) $invoices()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->count();

        $overdueCount = (int) $invoices()
            ->where('status', 'overdue')
            ->count();

        return [
            'invoiceCount' => (int) $invoices()->count(),
            'clientCount' => (int) Client::query()->count(),
            'totalBilled' => $totalBilledAll,
            'totalBilledMonth' => $totalBilledMonth,
            'monthlyTrend' => $monthlyTrend,
            'totalCollected' => $totalCollected,
            'collectionRate' => $collectionRate,
            'pendingBalance' => $pendingBalance,
            'pendingCount' => $pendingCount,
            'overdueCount' => $overdueCount,
            'monthlySeries' => $this->monthlySeries($now),
            'statusChart' => $this->statusChart(),
            'recentInvoices' => $invoices()
                ->latest('invoice_date')
                ->limit(6)
                ->get(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: float}>
     */
    /** Fixed Spanish month abbreviations (1-indexed) to avoid locale collisions. */
    private const MONTHS_ES = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    private function monthlySeries(CarbonImmutable $now): array
    {
        $series = [];
        // Anchor to the first of the month BEFORE subtracting, otherwise days
        // 29-31 overflow short months (e.g. Feb) and skip/duplicate a month.
        $base = $now->startOfMonth();

        for ($i = 5; $i >= 0; $i--) {
            $point = $base->subMonths($i);
            $endPoint = $point->endOfMonth();
            $series[] = [
                'label' => self::MONTHS_ES[$point->month],
                'value' => (float) Invoice::query()
                    ->where('document_type', Invoice::DOCUMENT_TYPE_INVOICE)
                    ->whereBetween('invoice_date', [$point, $endPoint])
                    ->sum('total'),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function statusChart(): array
    {
        $breakdown = Invoice::query()
            ->where('document_type', Invoice::DOCUMENT_TYPE_INVOICE)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusOrder = ['paid', 'issued', 'partially_paid', 'overdue', 'draft', 'cancelled'];
        $chart = [];

        foreach ($statusOrder as $status) {
            $count = (int) ($breakdown[$status] ?? 0);
            if ($count === 0) {
                continue;
            }
            $chart[] = [
                'status' => $status,
                'label' => InvoiceStatusLabel::label($status),
                'count' => $count,
            ];
        }

        return $chart;
    }
}
