<?php

namespace App\Services;

use App\Models\FiscalProfile;
use App\Models\ReportSetting;
use App\Models\TechnicalReport;
use App\Models\TechnicalReportNumberSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportNumberService
{
    public function generate(int $fiscalProfileId): string
    {
        return DB::transaction(function () use ($fiscalProfileId): string {
            $setting = $this->resolveSetting($fiscalProfileId, true);
            $nextNumber = max(1, (int) $setting->next_number);

            do {
                $candidate = $this->format($setting, $nextNumber);
                $nextNumber++;
            } while (TechnicalReport::query()->where('report_number', $candidate)->exists());

            $setting->update(['next_number' => $nextNumber]);

            return $candidate;
        });
    }

    public function preview(int $fiscalProfileId): string
    {
        $setting = DB::transaction(fn (): ?TechnicalReportNumberSetting => $this->resolveSetting($fiscalProfileId, true));

        return $setting === null ? '' : $this->format($setting, $setting->next_number);
    }

    public function ensureManualNumberIsAllowed(?string $reportNumber, ?TechnicalReport $report = null): void
    {
        if ($reportNumber === null || trim($reportNumber) === '' || $reportNumber === $report?->report_number) {
            return;
        }

        abort(422, 'La numeracion manual de informes no esta permitida.');
    }

    private function resolveSetting(int $fiscalProfileId, bool $create): ?TechnicalReportNumberSetting
    {
        $existing = TechnicalReportNumberSetting::query()
            ->where('fiscal_profile_id', $fiscalProfileId)
            ->lockForUpdate()
            ->first();

        if ($existing !== null || ! $create) {
            return $existing;
        }

        $profile = FiscalProfile::query()->findOrFail($fiscalProfileId);
        $legacy = ReportSetting::current();

        return TechnicalReportNumberSetting::query()->create([
            'fiscal_profile_id' => $profile->id,
            'prefix' => $legacy->report_prefix,
            'serie' => $this->uniqueSerie($profile),
            'next_number' => max(1, (int) $legacy->next_report_number),
            'number_length' => $legacy->number_length,
        ]);
    }

    private function format(TechnicalReportNumberSetting $setting, int $number): string
    {
        return $setting->prefix.trim($setting->serie, '-').'-'.str_pad((string) $number, $setting->number_length, '0', STR_PAD_LEFT);
    }

    private function uniqueSerie(FiscalProfile $profile): string
    {
        $parts = preg_split('/\s+/', trim(Str::ascii($profile->name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = collect($parts)->map(fn (string $part) => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $part) ?: '', 0, 1)))->implode('');
        $base = $base !== '' ? substr($base, 0, 8) : 'P'.$profile->id;
        $serie = $base;
        $suffix = 1;

        while (TechnicalReportNumberSetting::query()->where('serie', $serie)->where('fiscal_profile_id', '!=', $profile->id)->exists()) {
            $serie = $base.(++$suffix);
        }

        return $serie;
    }
}
