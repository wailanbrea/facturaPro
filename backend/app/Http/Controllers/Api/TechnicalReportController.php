<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechnicalReportRequest;
use App\Http\Requests\UpdateTechnicalReportRequest;
use App\Http\Resources\Api\TechnicalReportResource;
use App\Models\TechnicalReport;
use App\Services\TechnicalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TechnicalReportController extends Controller
{
    public function __construct(
        private readonly TechnicalReportService $reports,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = TechnicalReport::query()
            ->with(['createdBy', 'updatedBy'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('report_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_tax_id', 'like', "%{$search}%");
                });
            })
            ->latest('report_date')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return TechnicalReportResource::collection($reports);
    }

    public function store(StoreTechnicalReportRequest $request): TechnicalReportResource
    {
        $report = $this->reports->create($request->validated(), $request->user());

        return new TechnicalReportResource($report->load(['createdBy', 'updatedBy']));
    }

    public function show(TechnicalReport $technicalReport): TechnicalReportResource
    {
        return new TechnicalReportResource($technicalReport->load(['createdBy', 'updatedBy']));
    }

    public function update(UpdateTechnicalReportRequest $request, TechnicalReport $technicalReport): TechnicalReportResource
    {
        $report = $this->reports->update($technicalReport, $request->validated(), $request->user());

        return new TechnicalReportResource($report);
    }

    public function destroy(Request $request, TechnicalReport $technicalReport): Response|JsonResponse
    {
        $this->reports->cancelOrDelete($technicalReport, $request->user());

        return response()->noContent();
    }
}
