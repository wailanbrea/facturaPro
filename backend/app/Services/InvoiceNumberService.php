<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function generate(
        ?InvoiceNumberSetting $setting = null,
        CarbonInterface|string|null $date = null,
        ?int $fiscalProfileId = null,
        string $documentType = 'invoice',
        ?int $userId = null,
    ): string {
        $date = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? 'now');

        return DB::transaction(function () use ($setting, $date, $fiscalProfileId, $documentType, $userId): string {
            $lockedSetting = $setting !== null
                ? InvoiceNumberSetting::query()->whereKey($setting->getKey())->lockForUpdate()->first()
                : $this->resolveSetting($fiscalProfileId, $userId, $documentType);

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
            userId: $invoice->created_by,
        );
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

    /**
     * Resolve (and lock) the counter for a company x invoicing user x document type.
     *
     * Each (fiscal profile, user, document type) keeps its own continuous sequence.
     * When the per-user row does not exist yet it is created on the fly, inheriting
     * the prefix/format from the company-level template (user_id = null) and getting
     * a serie derived from the user's initials so numbers stay visually distinct.
     */
    private function resolveSetting(?int $fiscalProfileId, ?int $userId, string $documentType): InvoiceNumberSetting
    {
        $exact = InvoiceNumberSetting::query()
            ->where('fiscal_profile_id', $fiscalProfileId)
            ->where(fn ($q) => $userId === null ? $q->whereNull('user_id') : $q->where('user_id', $userId))
            ->where('document_type', $documentType)
            ->lockForUpdate()
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        // Legacy / system path (no user): preserve the previous behaviour of falling
        // back to the global counter before creating a company-level row.
        if ($userId === null) {
            $global = InvoiceNumberSetting::query()
                ->whereNull('fiscal_profile_id')
                ->whereNull('user_id')
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($global !== null && $fiscalProfileId === null) {
                return $global;
            }

            return InvoiceNumberSetting::query()->create([
                'fiscal_profile_id' => $fiscalProfileId,
                'user_id' => null,
                'document_type' => $documentType,
                'prefix' => $global?->prefix ?? $this->defaultPrefix($documentType),
                'next_number' => 1,
                'number_length' => $global?->number_length ?? 6,
            ]);
        }

        $template = $this->template($fiscalProfileId, $documentType);

        return InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $fiscalProfileId,
            'user_id' => $userId,
            'document_type' => $documentType,
            'prefix' => $template?->prefix ?? $this->defaultPrefix($documentType),
            'next_number' => 1,
            'number_length' => $template?->number_length ?? 6,
            'serie' => $this->serieForUser($userId, $fiscalProfileId, $documentType),
            'reset_yearly' => $template?->reset_yearly ?? false,
            'reset_monthly' => $template?->reset_monthly ?? false,
            'allow_manual_number' => $template?->allow_manual_number ?? false,
        ]);
    }

    /**
     * Company-level template (user_id = null) for a document type, falling back to
     * the global one, used to keep prefixes/format consistent across users.
     */
    private function template(?int $fiscalProfileId, string $documentType): ?InvoiceNumberSetting
    {
        return InvoiceNumberSetting::query()
            ->whereNull('user_id')
            ->where('document_type', $documentType)
            ->where(function ($q) use ($fiscalProfileId): void {
                $q->where('fiscal_profile_id', $fiscalProfileId);
                if ($fiscalProfileId !== null) {
                    $q->orWhereNull('fiscal_profile_id');
                }
            })
            ->orderByRaw('fiscal_profile_id IS NULL') // prefer company-specific over global
            ->first();
    }

    private function defaultPrefix(string $documentType): string
    {
        return $documentType === 'quotation' ? 'PRES-' : 'FAC-';
    }

    /**
     * Build a short, unique serie for a user within a company + document type
     * (e.g. "Juan Pérez" => "JP"). Collisions with another user that resolves to
     * the same initials get a numeric suffix.
     */
    private function serieForUser(int $userId, ?int $fiscalProfileId, string $documentType): string
    {
        $user = User::query()->find($userId);
        $base = $this->initials($user?->name ?? '');
        $base = $base !== '' ? $base : 'U' . $userId;

        $serie = $base;
        $suffix = 1;

        while (
            InvoiceNumberSetting::query()
                ->where('fiscal_profile_id', $fiscalProfileId)
                ->where('document_type', $documentType)
                ->where('serie', $serie)
                ->where('user_id', '!=', $userId)
                ->exists()
        ) {
            $serie = $base . (++$suffix);
        }

        return $serie;
    }

    private function initials(string $name): string
    {
        $name = strtr($name, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);

        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
