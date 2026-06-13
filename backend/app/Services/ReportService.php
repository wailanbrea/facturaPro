<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @param  array{date_from:?string,date_to:?string,currency_code:?string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $base = Invoice::query()
            ->where('document_type', Invoice::DOCUMENT_TYPE_INVOICE)
            ->where('status', '!=', 'cancelled')
            ->when($filters['date_from'], fn (Builder $query, string $date): Builder => $query->whereDate('invoice_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date): Builder => $query->whereDate('invoice_date', '<=', $date))
            ->when($filters['currency_code'], fn (Builder $query, string $code): Builder => $query->where('currency_code', $code));

        $overview = (clone $base)
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN balance_due > 0 AND due_date < CURRENT_DATE THEN 1 ELSE 0 END), 0) as overdue_count')
            ->first();

        $totalsByCurrency = (clone $base)
            ->select('currency_code', 'currency_symbol')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(amount_received), 0) as total_collected')
            ->selectRaw('COALESCE(SUM(balance_due), 0) as total_pending')
            ->groupBy('currency_code', 'currency_symbol')
            ->orderBy('currency_code')
            ->get();

        $totals = $this->totalsAvailable($totalsByCurrency)
            ? $totalsByCurrency->first()
            : null;

        $byDate = (clone $base)
            ->selectRaw('DATE(invoice_date) as invoice_day')
            ->select('currency_code', 'currency_symbol')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(amount_received), 0) as total_collected')
            ->selectRaw('COALESCE(SUM(balance_due), 0) as total_pending')
            ->groupBy(DB::raw('DATE(invoice_date)'), 'currency_code', 'currency_symbol')
            ->orderByDesc(DB::raw('DATE(invoice_date)'))
            ->orderBy('currency_code')
            ->limit(31)
            ->get();

        $byStatus = (clone $base)
            ->select('status', 'currency_code', 'currency_symbol')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(amount_received), 0) as total_collected')
            ->selectRaw('COALESCE(SUM(balance_due), 0) as total_pending')
            ->groupBy('status', 'currency_code', 'currency_symbol')
            ->orderBy('status')
            ->orderBy('currency_code')
            ->get();

        $byClient = (clone $base)
            ->select('client_name', 'currency_code', 'currency_symbol')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(amount_received), 0) as total_collected')
            ->selectRaw('COALESCE(SUM(balance_due), 0) as total_pending')
            ->groupBy('client_name', 'currency_code', 'currency_symbol')
            ->orderByDesc('total_invoiced')
            ->orderBy('client_name')
            ->limit(10)
            ->get();

        $overdueInvoices = (clone $base)
            ->select([
                'id',
                'invoice_number',
                'invoice_date',
                'due_date',
                'client_name',
                'currency_code',
                'currency_symbol',
                'total',
                'balance_due',
                'status',
            ])
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        return [
            'overview' => $overview,
            'totals' => $totals,
            'totalsByCurrency' => $totalsByCurrency,
            'byDate' => $byDate,
            'byStatus' => $byStatus,
            'byClient' => $byClient,
            'overdueInvoices' => $overdueInvoices,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ];
    }

    /**
     * @param  Collection<int, object>  $totalsByCurrency
     */
    public function totalsAvailable(Collection $totalsByCurrency): bool
    {
        return $totalsByCurrency->count() === 1;
    }
}
