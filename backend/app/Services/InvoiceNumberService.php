<?php

namespace App\Services;

use App\Models\FiscalProfile;
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

        return DB::transaction(function () use ($setting, $date, $fiscalProfileId, $documentType): string {
            $lockedSetting = $setting !== null
                ? InvoiceNumberSetting::query()->whereKey($setting->getKey())->lockForUpdate()->first()
                : $this->resolveSetting($fiscalProfileId, $documentType, true);

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
        );
    }

    public function preview(?int $fiscalProfileId = null, string $documentType = 'invoice', ?string $logoPath = null, ?int $userId = null): string
    {
        return DB::transaction(function () use ($fiscalProfileId, $documentType): string {
            $setting = $this->resolveSetting($fiscalProfileId, $documentType, false);

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
        bool $createIfMissing = true,
    ): ?InvoiceNumberSetting
    {
        $exact = InvoiceNumberSetting::query()
            ->where('fiscal_profile_id', $fiscalProfileId)
            ->where('document_type', $documentType)
            ->lockForUpdate()
            ->first();

        if ($exact !== null) {
            if ($fiscalProfileId !== null && blank($exact->serie)) {
                $exact->serie = $this->serieForProfile($fiscalProfileId, $documentType);
                $exact->save();
            }

            return $exact;
        }

        if (! $createIfMissing) {
            return null;
        }

        if ($fiscalProfileId === null) {
            return InvoiceNumberSetting::query()->create([
                'fiscal_profile_id' => null,
                'document_type' => $documentType,
                'prefix' => $this->defaultPrefix($documentType),
                'next_number' => 1,
                'number_length' => 6,
            ]);
        }

        $template = $this->template($fiscalProfileId, $documentType);

        return InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $fiscalProfileId,
            'document_type' => $documentType,
            'prefix' => $template?->prefix ?? $this->defaultPrefix($documentType),
            'next_number' => $this->nextNumberForProfile($fiscalProfileId, $documentType),
            'number_length' => $template?->number_length ?? 6,
            'serie' => $this->serieForProfile($fiscalProfileId, $documentType),
            'reset_yearly' => $template?->reset_yearly ?? false,
            'reset_monthly' => $template?->reset_monthly ?? false,
            'allow_manual_number' => false,
        ]);
    }

    private function template(?int $fiscalProfileId, string $documentType): ?InvoiceNumberSetting
    {
        return InvoiceNumberSetting::query()
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

    private function nextNumberForProfile(int $fiscalProfileId, string $documentType): int
    {
        $next = InvoiceNumberSetting::query()
            ->where('fiscal_profile_id', $fiscalProfileId)
            ->where('document_type', $documentType)
            ->max('next_number');

        return max(1, (int) ($next ?? 1));
    }

    private function defaultPrefix(string $documentType): string
    {
        return $documentType === 'quotation' ? 'PRES-' : 'FAC-';
    }

    private function serieForProfile(int $fiscalProfileId, string $documentType): string
    {
        $profile = FiscalProfile::query()->find($fiscalProfileId);
        $base = $this->initials($profile?->name ?? '');
        $base = $base !== '' ? $base : 'P' . $fiscalProfileId;

        $serie = $base;
        $suffix = 1;

        while (
            InvoiceNumberSetting::query()
                ->where('document_type', $documentType)
                ->where('serie', $serie)
                ->where(function ($query) use ($fiscalProfileId): void {
                    $query->where('fiscal_profile_id', '!=', $fiscalProfileId)
                        ->orWhereNull('fiscal_profile_id');
                })
                ->exists()
        ) {
            $serie = $base . (++$suffix);
        }

        return $serie;
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
