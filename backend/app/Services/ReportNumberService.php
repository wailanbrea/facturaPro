<?php

namespace App\Services;

use App\Models\ReportSetting;
use App\Models\TechnicalReport;
use Illuminate\Support\Facades\DB;

class ReportNumberService
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $setting = ReportSetting::query()->lockForUpdate()->first()
                ?? ReportSetting::query()->create(ReportSetting::defaults());

            $nextNumber = max(1, (int) $setting->next_report_number);

            do {
                $candidate = $setting->report_prefix.str_pad((string) $nextNumber, $setting->number_length, '0', STR_PAD_LEFT);
                $nextNumber++;
            } while (TechnicalReport::query()->where('report_number', $candidate)->exists());

            $setting->update(['next_report_number' => $nextNumber]);

            return $candidate;
        });
    }

    public function ensureManualNumberIsAllowed(?string $reportNumber, ?TechnicalReport $report = null): void
    {
        if ($reportNumber === null || trim($reportNumber) === '' || $reportNumber === $report?->report_number) {
            return;
        }

        abort_if(! ReportSetting::current()->allow_manual_number, 422, 'La numeracion manual de informes no esta permitida.');
    }
}
