<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FiscalProfile;
use App\Models\ReportSetting;
use App\Models\TechnicalReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TechnicalReportService
{
    public const DRAFT = 'draft';
    public const ISSUED = 'issued';
    public const CANCELLED = 'cancelled';

    public function __construct(
        private readonly ReportNumberService $numberService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?User $user = null): TechnicalReport
    {
        return DB::transaction(function () use ($data, $user): TechnicalReport {
            $this->numberService->ensureManualNumberIsAllowed($data['report_number'] ?? null);

            return TechnicalReport::query()->create($this->payload($data, null, $user));
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(TechnicalReport $report, array $data, ?User $user = null): TechnicalReport
    {
        abort_if($report->status === self::CANCELLED, 409, 'No se puede editar un informe anulado.');

        return DB::transaction(function () use ($report, $data, $user): TechnicalReport {
            $this->numberService->ensureManualNumberIsAllowed($data['report_number'] ?? null, $report);
            $report->update($this->payload($data, $report, $user));

            return $report->fresh(['client', 'fiscalProfile', 'createdBy', 'updatedBy']);
        });
    }

    public function issue(TechnicalReport $report, ?User $user = null): TechnicalReport
    {
        if ($report->status !== self::CANCELLED) {
            $report->update([
                'status' => self::ISSUED,
                'updated_by' => $user?->id,
            ]);
        }

        return $report->fresh(['client', 'fiscalProfile', 'createdBy', 'updatedBy']);
    }

    public function cancelOrDelete(TechnicalReport $report, ?User $user = null): void
    {
        if ($report->status === self::DRAFT) {
            $report->delete();

            return;
        }

        $report->update([
            'status' => self::CANCELLED,
            'updated_by' => $user?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $settings = ReportSetting::current();

        return [
            'report_date' => now()->toDateString(),
            'status' => self::DRAFT,
            // El formulario arranca con una sola seccion (Titulo + Texto);
            // las demas se agregan bajo demanda con el boton "Agregar seccion".
            'section_1_title' => $settings->section_1_default_title,
            'section_2_title' => null,
            'section_3_title' => null,
            'section_4_title' => null,
            'intro_text' => $settings->intro_text,
            'final_text' => $settings->final_text,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?TechnicalReport $report = null, ?User $user = null): array
    {
        $settings = ReportSetting::current();
        $profile = FiscalProfile::query()->findOrFail($data['fiscal_profile_id'] ?? $report?->fiscal_profile_id);
        $client = isset($data['client_id']) && $data['client_id'] !== ''
            ? Client::query()->find($data['client_id'])
            : null;

        $reportNumber = trim((string) ($data['report_number'] ?? ''));
        if ($reportNumber === '') {
            $reportNumber = $report?->report_number ?: $this->numberService->generate();
        }

        $recipientName = trim((string) ($data['recipient_name'] ?? ''));
        $recipientTaxId = trim((string) ($data['recipient_tax_id'] ?? ''));
        $recipientAddress = trim((string) ($data['recipient_address'] ?? ''));

        return [
            'report_number' => $reportNumber,
            'report_date' => $data['report_date'] ?? $report?->report_date?->toDateString() ?? now()->toDateString(),
            'fiscal_profile_id' => $profile->id,
            'seller_name' => $profile->name,
            'seller_tax_id' => $profile->tax_id,
            'seller_address' => $profile->address,
            'seller_city' => $profile->city,
            'client_id' => $client?->id,
            'recipient_name' => $recipientName !== '' ? $recipientName : (string) $client?->name,
            'recipient_tax_id' => $recipientTaxId !== '' ? $recipientTaxId : $client?->tax_id,
            'recipient_address' => $recipientAddress !== '' ? $recipientAddress : trim((string) $client?->address),
            'seller_logo_path' => array_key_exists('logo_path', $data)
                ? (($data['logo_path'] ?? '') !== '' ? $data['logo_path'] : $profile->logo_path)
                : ($report?->seller_logo_path ?? $profile->logo_path),
            'section_1_title' => $data['section_1_title'] ?? $report?->section_1_title ?? $settings->section_1_default_title,
            'section_1_content' => $data['section_1_content'] ?? $report?->section_1_content,
            // Las secciones 2-4 son opcionales: si el formulario las envia vacias
            // se limpian (no se rellenan con titulos por defecto).
            'section_2_title' => array_key_exists('section_2_title', $data) ? $data['section_2_title'] : $report?->section_2_title,
            'section_2_content' => array_key_exists('section_2_content', $data) ? $data['section_2_content'] : $report?->section_2_content,
            'section_3_title' => array_key_exists('section_3_title', $data) ? $data['section_3_title'] : $report?->section_3_title,
            'section_3_content' => array_key_exists('section_3_content', $data) ? $data['section_3_content'] : $report?->section_3_content,
            'section_4_title' => array_key_exists('section_4_title', $data) ? $data['section_4_title'] : $report?->section_4_title,
            'section_4_content' => array_key_exists('section_4_content', $data) ? $data['section_4_content'] : $report?->section_4_content,
            'intro_text' => array_key_exists('intro_text', $data) ? $data['intro_text'] : ($report?->intro_text ?? $settings->intro_text),
            'final_text' => array_key_exists('final_text', $data) ? $data['final_text'] : ($report?->final_text ?? $settings->final_text),
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $report?->notes,
            'status' => $data['status'] ?? $report?->status ?? self::DRAFT,
            'created_by' => $report?->created_by ?? $user?->id,
            'updated_by' => $user?->id,
        ];
    }
}
