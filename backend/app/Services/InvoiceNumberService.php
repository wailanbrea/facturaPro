<?php

namespace App\Services;

use App\Models\FiscalProfile;
use App\Models\FiscalProfileLogo;
use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceNumberService
{
    public function generate(
        ?InvoiceNumberSetting $setting = null,
        CarbonInterface|string|null $date = null,
        ?int $fiscalProfileId = null,
        string $documentType = 'invoice',
        ?int $userId = null,
        ?string $logoPath = null,
    ): string {
        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? 'now');

        return DB::transaction(function () use ($setting, $date, $fiscalProfileId, $documentType, $logoPath, $userId): string {
            $lockedSetting = $setting !== null
                ? InvoiceNumberSetting::query()->whereKey($setting->getKey())->lockForUpdate()->first()
                : $this->resolveSetting($fiscalProfileId, $documentType, $logoPath, true, $userId ?? auth()->id());

            $this->resetSequenceIfNeeded($lockedSetting, $date);

            do {
                $invoiceNumber = $this->format($lockedSetting);
                $lockedSetting->next_number++;
            } while (Invoice::query()->where('invoice_number', $invoiceNumber)->exists());

            $lockedSetting->save();

            return $invoiceNumber;
        });
    }

    public function generateForInvoice(Invoice $invoice): string
    {
        return $this->generate(
            date: $invoice->invoice_date,
            fiscalProfileId: $invoice->fiscal_profile_id,
            documentType: $invoice->document_type ?? 'invoice',
            logoPath: $invoice->logo_path,
            userId: $invoice->created_by ?? auth()->id(),
        );
    }

    public function preview(?int $fiscalProfileId = null, string $documentType = 'invoice', ?string $logoPath = null, ?int $userId = null): string
    {
        return DB::transaction(function () use ($fiscalProfileId, $documentType, $logoPath, $userId): string {
            $setting = $this->resolveSetting($fiscalProfileId, $documentType, $logoPath, false, $userId ?? auth()->id());

            return $setting ? $this->format($setting) : '';
        });
    }

    public function format(InvoiceNumberSetting $setting): string
    {
        $number = str_pad(
            (string) $setting->next_number,
            $setting->number_length,
            '0',
            STR_PAD_LEFT,
        );

        $serie = $setting->serie ? trim($setting->serie, '-') . '-' : '';

        return $setting->prefix . $serie . $number;
    }

    private function resolveSetting(
        ?int $fiscalProfileId,
        string $documentType,
        ?string $logoPath = null,
        bool $createIfMissing = true,
        ?int $userId = null,
    ): ?InvoiceNumberSetting
    {
        $logoPath = $this->normalizeLogoPath($logoPath);

        $exact = $this->exactSettingQuery($fiscalProfileId, $documentType, $logoPath, $userId)
            ->lockForUpdate()
            ->first();

        if ($exact !== null) {
            if ($fiscalProfileId !== null && blank($exact->serie)) {
                $exact->serie = $this->serieForProfile($fiscalProfileId, $documentType, $logoPath);
                $exact->save();
            }

            return $exact;
        }

        if (! $createIfMissing) {
            return null;
        }

        if ($logoPath !== null) {
            throw new RuntimeException('No hay numeracion configurada para el logo seleccionado.');
        }

        if ($fiscalProfileId === null) {
            return InvoiceNumberSetting::query()->create([
                'fiscal_profile_id' => null,
                'user_id' => $userId,
                'logo_path' => null,
                'document_type' => $documentType,
                'prefix' => $this->defaultPrefix($documentType),
                'next_number' => 1,
                'number_length' => 6,
            ]);
        }

        $template = $this->template($fiscalProfileId, $documentType);

        return InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $fiscalProfileId,
            'user_id' => $userId,
            'logo_path' => $logoPath,
            'document_type' => $documentType,
            'prefix' => $template?->prefix ?? $this->defaultPrefix($documentType),
            'next_number' => $this->nextNumberForProfile($fiscalProfileId, $documentType, $logoPath),
            'number_length' => $template?->number_length ?? 6,
            'serie' => $this->serieForProfile($fiscalProfileId, $documentType, $logoPath),
            'reset_yearly' => $template?->reset_yearly ?? false,
            'reset_monthly' => $template?->reset_monthly ?? false,
            'allow_manual_number' => false,
        ]);
    }

    private function exactSettingQuery(?int $fiscalProfileId, string $documentType, ?string $logoPath, ?int $userId = null)
    {
        return InvoiceNumberSetting::query()
            ->where('fiscal_profile_id', $fiscalProfileId)
            ->where(function ($query) use ($userId): void {
                $query->whereNull('user_id');

                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->where('logo_path', $logoPath)
            ->where('document_type', $documentType)
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw("CASE WHEN serie LIKE '%-%' THEN 0 ELSE 1 END")
            ->orderBy('id');
    }

    private function template(?int $fiscalProfileId, string $documentType): ?InvoiceNumberSetting
    {
        return InvoiceNumberSetting::query()
            ->whereNull('user_id')
            ->whereNull('logo_path')
            ->where('document_type', $documentType)
            ->where(function ($q) use ($fiscalProfileId): void {
                $q->where('fiscal_profile_id', $fiscalProfileId);
                if ($fiscalProfileId !== null) {
                    $q->orWhereNull('fiscal_profile_id');
                }
            })
            ->orderByRaw('fiscal_profile_id IS NULL')
            ->first();
    }

    private function nextNumberForProfile(int $fiscalProfileId, string $documentType, ?string $logoPath = null): int
    {
        $logoPath = $this->normalizeLogoPath($logoPath);
        $next = InvoiceNumberSetting::query()
            ->where('fiscal_profile_id', $fiscalProfileId)
            ->where('document_type', $documentType)
            ->where('logo_path', $logoPath)
            ->max('next_number');

        return max(1, (int) ($next ?? 1));
    }

    private function defaultPrefix(string $documentType): string
    {
        return $documentType === 'quotation' ? 'PRES-' : 'FAC-';
    }

    private function serieForProfile(int $fiscalProfileId, string $documentType, ?string $logoPath = null): string
    {
        $profile = FiscalProfile::query()->find($fiscalProfileId);
        $base = $this->initials($profile?->name ?? '');
        $base = $base !== '' ? $base : 'P' . $fiscalProfileId;

        if ($logoPath !== null) {
            $logo = FiscalProfileLogo::query()
                ->where('fiscal_profile_id', $fiscalProfileId)
                ->where('path', $logoPath)
                ->first();
            $logoBase = $this->initials($logo?->label ?: pathinfo($logoPath, PATHINFO_FILENAME));
            $base .= $logoBase !== '' ? $logoBase : 'L';
        }

        $serie = $base;
        $suffix = 1;

        while (
            InvoiceNumberSetting::query()
                ->where('document_type', $documentType)
                ->where('serie', $serie)
                ->where(function ($query) use ($fiscalProfileId, $logoPath): void {
                    $query->where('fiscal_profile_id', '!=', $fiscalProfileId)
                        ->orWhereNull('fiscal_profile_id');
                    if ($logoPath !== null) {
                        $query->orWhere('logo_path', '!=', $logoPath)
                            ->orWhereNull('logo_path');
                    }
                })
                ->exists()
        ) {
            $serie = $base . (++$suffix);
        }

        return $serie;
    }

    private function normalizeLogoPath(?string $logoPath): ?string
    {
        $logoPath = is_string($logoPath) ? trim($logoPath) : '';

        return $logoPath !== '' ? $logoPath : null;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            $clean = preg_replace('/[^A-Za-z]/', '', $part);
            if ($clean !== '') {
                $letters .= strtoupper($clean[0]);
            }
            if (strlen($letters) >= 2) {
                break;
            }
        }

        return $letters;
    }

    private function resetSequenceIfNeeded(InvoiceNumberSetting $setting, CarbonImmutable $date): void
    {
        $shouldResetYear = $setting->reset_yearly && $setting->current_year !== $date->year;
        $shouldResetMonth = $setting->reset_monthly
            && ($setting->current_year !== $date->year || $setting->current_month !== $date->month);

        if ($shouldResetYear || $shouldResetMonth) {
            $setting->next_number = 1;
        }

        if ($setting->reset_yearly || $setting->reset_monthly) {
            $setting->current_year = $date->year;
        }

        if ($setting->reset_monthly) {
            $setting->current_month = $date->month;
        }
    }
}
