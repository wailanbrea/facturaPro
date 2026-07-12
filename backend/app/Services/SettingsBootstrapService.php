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

class SettingsBootstrapService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $fiscalProfiles = auth()->user()?->availableFiscalProfiles()
            ?? FiscalProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        return [
            'currencies' => Currency::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get(),
            'taxes' => Tax::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'payment_terms' => PaymentTerm::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('days')->get(),
            'warranties' => Warranty::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('duration_months')->get(),
            'bank_accounts' => BankAccount::query()->with('currency')->where('is_active', true)->orderByDesc('is_default')->orderBy('label')->get(),
            'fiscal_profiles' => $fiscalProfiles->load('logos'),
            'legal_texts' => LegalText::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'invoice_number_settings' => InvoiceNumberSetting::query()->get(),
            'settings' => Setting::query()->get()->groupBy('group')->map->pluck('value', 'key'),
        ];
    }
}
