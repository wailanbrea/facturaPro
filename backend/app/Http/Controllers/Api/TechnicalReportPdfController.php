<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TechnicalReport;
use App\Services\ReportPdfService;
use App\Services\TechnicalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalReportPdfController extends Controller
{
    public function __construct(
        private readonly ReportPdfService $pdfService,
        private readonly TechnicalReportService $reports,
    ) {
    }

    public function preview(TechnicalReport $technicalReport): Response
    {
        return response()->make(
            view('pdf.report', [
                'report' => $technicalReport->load(['client', 'fiscalProfile', 'createdBy']),
            ])->render(),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function generate(Request $request, TechnicalReport $technicalReport): JsonResponse
    {
        if ($technicalReport->status === TechnicalReportService::CANCELLED) {
            return response()->json(['message' => 'No se puede generar PDF de un informe anulado.'], 409);
        }

        $report = $this->reports->issue($technicalReport, $request->user());

        try {
            $path = $this->pdfService->generate($report);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        $absolutePath = Storage::disk('public')->path($path);

        $report->update([
            'pdf_path' => $path,
            'pdf_sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null,
        ]);

        return response()->json(['pdf_path' => $path]);
    }

    public function download(TechnicalReport $technicalReport): StreamedResponse|JsonResponse
    {
        if (! $technicalReport->pdf_path || ! Storage::disk('public')->exists($technicalReport->pdf_path)) {
            return response()->json(['message' => 'El PDF no esta disponible para este informe.'], 404);
        }

        return Storage::disk('public')->download($technicalReport->pdf_path);
    }
}
