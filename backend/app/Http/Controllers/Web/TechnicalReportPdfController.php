<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechnicalReportRequest;
use App\Models\Client;
use App\Models\FiscalProfile;
use App\Models\TechnicalReport;
use App\Services\ReportPdfService;
use App\Services\TechnicalReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalReportPdfController extends Controller
{
    public function __construct(
        private readonly ReportPdfService $pdfService,
        private readonly TechnicalReportService $reports,
    ) {
    }

    public function previewDraft(StoreTechnicalReportRequest $request): View
    {
        $data = $request->validated();
        $profile = FiscalProfile::query()->findOrFail($data['fiscal_profile_id']);
        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $report = new TechnicalReport([
            ...$data,
            'report_number' => $data['report_number'] ?? 'BORRADOR',
            'seller_name' => $profile->name,
            'seller_tax_id' => $profile->tax_id,
            'seller_address' => $profile->address,
            'seller_city' => $profile->city,
            'seller_logo_path' => ($data['logo_path'] ?? '') !== '' ? $data['logo_path'] : $profile->logo_path,
            'recipient_tax_id' => $data['recipient_tax_id'] ?? $client?->tax_id,
            'status' => $data['status'] ?? TechnicalReportService::DRAFT,
        ]);
        $report->setRelation('client', $client);
        $report->setRelation('fiscalProfile', $profile);
        $report->setRelation('createdBy', null);

        return view('pdf.report', ['report' => $report]);
    }

    public function preview(TechnicalReport $technicalReport): View
    {
        return view('pdf.report', [
            'report' => $technicalReport->load(['client', 'fiscalProfile', 'createdBy']),
        ]);
    }

    public function generate(Request $request, TechnicalReport $technicalReport): RedirectResponse
    {
        if ($technicalReport->status === TechnicalReportService::CANCELLED) {
            return back()->withErrors(['report' => 'No se puede generar PDF de un informe anulado.']);
        }

        $report = $this->reports->issue($technicalReport, $request->user());

        try {
            $path = $this->pdfService->generate($report);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['report' => $exception->getMessage()]);
        }

        $report->update(['pdf_path' => $path]);

        return back()->with('status', 'PDF del informe generado correctamente.');
    }

    public function download(TechnicalReport $technicalReport): StreamedResponse|RedirectResponse
    {
        if (! $technicalReport->pdf_path || ! Storage::disk('public')->exists($technicalReport->pdf_path)) {
            return back()->withErrors(['report' => 'El PDF no esta disponible para este informe.']);
        }

        return Storage::disk('public')->download($technicalReport->pdf_path);
    }
}
