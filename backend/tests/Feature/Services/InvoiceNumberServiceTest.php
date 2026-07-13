<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\FiscalProfileLogo;
use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use App\Services\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_the_next_invoice_number_and_increments_sequence(): void
    {
        $setting = InvoiceNumberSetting::query()->create([
            'prefix' => 'FAC-',
            'next_number' => 1,
            'number_length' => 6,
        ]);

        $number = app(InvoiceNumberService::class)->generate($setting, '2026-05-21');

        $this->assertSame('FAC-000001', $number);
        $this->assertSame(2, $setting->fresh()->next_number);
    }

    public function test_it_skips_existing_invoice_numbers(): void
    {
        $setting = InvoiceNumberSetting::query()->create([
            'prefix' => 'FAC-',
            'next_number' => 1,
            'number_length' => 6,
        ]);

        $currency = Currency::query()->create([
            'name' => 'Peso Dominicano',
            'code' => 'DOP',
            'symbol' => 'RD$',
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'decimal_places' => 2,
            'symbol_position' => 'before',
        ]);

        $client = Client::query()->create(['name' => 'Cliente Prueba']);

        Invoice::query()->create([
            'invoice_number' => 'FAC-000001',
            'invoice_date' => '2026-05-21',
            'client_id' => $client->id,
            'client_name' => $client->name,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
        ]);

        $number = app(InvoiceNumberService::class)->generate($setting, '2026-05-21');

        $this->assertSame('FAC-000002', $number);
        $this->assertSame(3, $setting->fresh()->next_number);
    }

    public function test_each_fiscal_profile_keeps_its_own_sequence(): void
    {
        $empresa1 = FiscalProfile::query()->create(['name' => 'Luis Amauris']);
        $empresa2 = FiscalProfile::query()->create(['name' => 'Pamela Mishell']);

        InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => null,
            'document_type' => 'invoice',
            'prefix' => 'FAC-',
            'next_number' => 1,
            'number_length' => 6,
        ]);

        $service = app(InvoiceNumberService::class);

        $gen = fn (int $profileId): string => $service->generate(
            date: '2026-06-07',
            fiscalProfileId: $profileId,
            documentType: 'invoice',
        );

        $this->assertSame('FAC-LA-000001', $gen($empresa1->id));
        $this->assertSame('FAC-LA-000002', $gen($empresa1->id));
        $this->assertSame('FAC-PM-000001', $gen($empresa2->id));
        $this->assertSame('FAC-PM-000002', $gen($empresa2->id));

        $this->assertSame(2, InvoiceNumberSetting::query()
            ->whereNotNull('fiscal_profile_id')
            ->whereNull('user_id')
            ->count());
    }

    public function test_each_logo_keeps_its_own_sequence_within_a_fiscal_profile(): void
    {
        $profile = FiscalProfile::query()->create(['name' => 'Pamela Mishell']);
        $logoA = FiscalProfileLogo::query()->create([
            'fiscal_profile_id' => $profile->id,
            'path' => 'logos/pamela-a.png',
            'label' => 'Taller Norte',
            'is_default' => true,
        ]);
        $logoB = FiscalProfileLogo::query()->create([
            'fiscal_profile_id' => $profile->id,
            'path' => 'logos/pamela-b.png',
            'label' => 'Taller Sur',
            'is_default' => false,
        ]);

        InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => null,
            'document_type' => 'invoice',
            'prefix' => 'FAC-',
            'next_number' => 1,
            'number_length' => 6,
        ]);
        InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $profile->id,
            'logo_path' => $logoA->path,
            'document_type' => 'invoice',
            'prefix' => 'FAC-',
            'serie' => 'PA-AIR',
            'next_number' => 1,
            'number_length' => 6,
        ]);
        InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $profile->id,
            'logo_path' => $logoB->path,
            'document_type' => 'invoice',
            'prefix' => 'FAC-',
            'serie' => 'PA-CAL',
            'next_number' => 1,
            'number_length' => 6,
        ]);

        $service = app(InvoiceNumberService::class);

        $this->assertSame('FAC-PA-AIR-000001', $service->preview($profile->id, 'invoice', $logoA->path));
        $this->assertSame('FAC-PA-CAL-000001', $service->preview($profile->id, 'invoice', $logoB->path));

        $this->assertSame('FAC-PA-AIR-000001', $service->generate(
            date: '2026-07-13',
            fiscalProfileId: $profile->id,
            documentType: 'invoice',
            logoPath: $logoA->path,
        ));
        $this->assertSame('FAC-PA-AIR-000002', $service->generate(
            date: '2026-07-13',
            fiscalProfileId: $profile->id,
            documentType: 'invoice',
            logoPath: $logoA->path,
        ));
        $this->assertSame('FAC-PA-CAL-000001', $service->generate(
            date: '2026-07-13',
            fiscalProfileId: $profile->id,
            documentType: 'invoice',
            logoPath: $logoB->path,
        ));

        $this->assertSame(2, InvoiceNumberSetting::query()
            ->where('fiscal_profile_id', $profile->id)
            ->whereNotNull('logo_path')
            ->where('document_type', 'invoice')
            ->count());
    }

    public function test_preview_does_not_create_a_missing_logo_sequence(): void
    {
        $profile = FiscalProfile::query()->create(['name' => 'Pamela Mishell']);
        $logo = FiscalProfileLogo::query()->create([
            'fiscal_profile_id' => $profile->id,
            'path' => 'logos/pamela-air.png',
            'label' => 'AIR',
            'is_default' => true,
        ]);

        $service = app(InvoiceNumberService::class);

        $this->assertSame('', $service->preview($profile->id, 'invoice', $logo->path));
        $this->assertDatabaseMissing('invoice_number_settings', [
            'fiscal_profile_id' => $profile->id,
            'logo_path' => $logo->path,
            'document_type' => 'invoice',
        ]);
    }
}
