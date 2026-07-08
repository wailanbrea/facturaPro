<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechnicalReportRequest;
use App\Http\Requests\UpdateTechnicalReportRequest;
use App\Models\Client;
use App\Models\FiscalProfile;
use App\Models\ReportSetting;
use App\Models\TechnicalReport;
use App\Services\TechnicalReportService;
use App\Support\TechnicalReportStatusLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicalReportController extends Controller
{
    public function __construct(
        private readonly TechnicalReportService $reports,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:draft,issued,cancelled'],
        ]);

        $reports = TechnicalReport::query()
            ->with(['createdBy'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('report_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_tax_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['client'] ?? null, fn ($query, string $client) => $query->where('recipient_name', 'like', "%{$client}%"))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('report_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('report_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('report_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('technical-reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'statusOptions' => TechnicalReportStatusLabel::options(),
        ]);
    }

    public function create(): View
    {
        return view('technical-reports.create', [
            ...$this->catalogs(),
            'report' => new TechnicalReport($this->reports->defaults()),
            'action' => route('web.technical-reports.store'),
            'method' => 'POST',
            'submitLabel' => 'Guardar borrador',
        ]);
    }

    public function store(StoreTechnicalReportRequest $request): RedirectResponse
    {
        $report = $this->reports->create($request->validated(), $request->user());

        return redirect()
            ->route('web.technical-reports.show', $report)
            ->with('status', 'Informe creado correctamente.');
    }

    public function show(TechnicalReport $technicalReport): View
    {
        return view('technical-reports.show', [
            'report' => $technicalReport->load(['client', 'fiscalProfile', 'createdBy', 'updatedBy']),
        ]);
    }

    public function edit(TechnicalReport $technicalReport): View|RedirectResponse
    {
        if ($technicalReport->status === TechnicalReportService::CANCELLED) {
            return redirect()
                ->route('web.technical-reports.show', $technicalReport)
                ->withErrors(['report' => 'No se puede editar un informe anulado.']);
        }

        return view('technical-reports.edit', [
            ...$this->catalogs(),
            'report' => $technicalReport,
            'action' => route('web.technical-reports.update', $technicalReport),
            'method' => 'PUT',
            'submitLabel' => 'Actualizar informe',
        ]);
    }

    public function update(UpdateTechnicalReportRequest $request, TechnicalReport $technicalReport): RedirectResponse
    {
        $report = $this->reports->update($technicalReport, $request->validated(), $request->user());

        return redirect()
            ->route('web.technical-reports.show', $report)
            ->with('status', 'Informe actualizado correctamente.');
    }

    public function destroy(Request $request, TechnicalReport $technicalReport): RedirectResponse
    {
        $this->reports->cancelOrDelete($technicalReport, $request->user());

        return redirect()
            ->route('web.technical-reports.index')
            ->with('status', 'Informe eliminado o anulado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        return [
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'fiscalProfiles' => FiscalProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'reportSetting' => ReportSetting::current(),
            'statusOptions' => TechnicalReportStatusLabel::options(),
            'availableLogos' => \App\Support\AvailableLogos::list(),
        ];
    }
}
