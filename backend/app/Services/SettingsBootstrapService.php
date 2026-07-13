<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\InvoiceNumberSetting;
use App\Models\LegalText;
use App\Models\PaymentTerm;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Warranty;
use App\Services\InvoiceNumberService;

class SettingsBootstrapService
{
    public function __construct(private readonly InvoiceNumberService $invoiceNumberService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $fiscalProfiles = auth()->user()?->availableFiscalProfiles()
            ?? FiscalProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        $fiscalProfiles = $fiscalProfiles->load('logos');
        $invoiceNumberPreviews = $this->invoiceNumberPreviews($fiscalProfiles);

        return [
            'currencies' => Currency::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get(),
            'taxes' => Tax::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'payment_terms' => PaymentTerm::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('days')->get(),
            'warranties' => Warranty::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('duration_months')->get(),
            'bank_accounts' => BankAccount::query()->with('currency')->where('is_active', true)->orderByDesc('is_default')->orderBy('label')->get(),
            'fiscal_profiles' => $fiscalProfiles,
            'legal_texts' => LegalText::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'invoice_number_settings' => InvoiceNumberSetting::query()->get(),
            'invoice_number_previews' => $invoiceNumberPreviews,
            'settings' => Setting::query()->get()->groupBy('group')->map->pluck('value', 'key'),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FiscalProfile>  $fiscalProfiles
     * @return array<int, array<string, mixed>>
     */
    private function invoiceNumberPreviews(\Illuminate\Support\Collection $fiscalProfiles): array
    {
        return $fiscalProfiles->mapWithKeys(function (FiscalProfile $profile): array {
            return [
                $profile->id => [
                    'default' => [
                        'invoice' => $this->invoiceNumberService->preview($profile->id, 'invoice'),
                        'quotation' => $this->invoiceNumberService->preview($profile->id, 'quotation'),
                    ],
                    'logos' => $profile->logos->mapWithKeys(fn ($logo): array => [
                        $logo->path => [
                            'invoice' => $this->invoiceNumberService->preview($profile->id, 'invoice', $logo->path),
                            'quotation' => $this->invoiceNumberService->preview($profile->id, 'quotation', $logo->path),
                        ],
                    ])->all(),
                ],
            ];
        })->all();
    }
}
