<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
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

        return view('reports.index', [
            ...$data,
            'filters' => [
                'dateFrom' => $filters['date_from'],
                'dateTo' => $filters['date_to'],
                'currencyCode' => $filters['currency_code'],
            ],
            'canShowUnifiedMoneyTotals' => $reports->totalsAvailable($data['totalsByCurrency']),
        ]);
    }
}
