<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\Warranty;
use App\Services\SettingsBootstrapService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function bootstrap(SettingsBootstrapService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->get()]);
    }

    public function currencies(): JsonResponse
    {
        return response()->json([
            'data' => Currency::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get(),
        ]);
    }

    public function taxes(): JsonResponse
    {
        return response()->json([
            'data' => Tax::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function paymentTerms(): JsonResponse
    {
        return response()->json([
            'data' => PaymentTerm::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('days')->get(),
        ]);
    }

    public function warranties(): JsonResponse
    {
        return response()->json([
            'data' => Warranty::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('duration_months')->get(),
        ]);
    }

    public function bankAccounts(): JsonResponse
    {
        return response()->json([
            'data' => BankAccount::query()->with('currency')->where('is_active', true)->orderByDesc('is_default')->orderBy('label')->get(),
        ]);
    }

    public function fiscalProfiles(): JsonResponse
    {
        $profiles = request()->user()?->availableFiscalProfiles()
            ?? FiscalProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        return response()->json([
            'data' => $profiles->load('logos'),
        ]);
    }
}
