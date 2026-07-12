<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
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
}
