<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use App\Models\User;
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

    public function test_each_company_and_user_pair_keeps_its_own_continuous_sequence(): void
    {
        $juan = User::query()->create(['name' => 'Juan Pérez', 'email' => 'juan@example.com', 'password' => 'secret']);
        $pedro = User::query()->create(['name' => 'Pedro Ruiz', 'email' => 'pedro@example.com', 'password' => 'secret']);

        $empresa1 = FiscalProfile::query()->create(['name' => 'Empresa Uno']);
        $empresa2 = FiscalProfile::query()->create(['name' => 'Empresa Dos']);

        // Company-level templates that carry the per-company prefix.
        InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $empresa1->id,
            'document_type' => 'invoice',
            'prefix' => 'FAC-E1-',
            'next_number' => 1,
            'number_length' => 6,
        ]);
        InvoiceNumberSetting::query()->create([
            'fiscal_profile_id' => $empresa2->id,
            'document_type' => 'invoice',
            'prefix' => 'FAC-E2-',
            'next_number' => 1,
            'number_length' => 6,
        ]);

        $service = app(InvoiceNumberService::class);

        $gen = fn (int $profileId, int $userId): string => $service->generate(
            date: '2026-06-07',
            fiscalProfileId: $profileId,
            documentType: 'invoice',
            userId: $userId,
        );

        // Empresa 1: Juan and Pedro each start their own counter.
        $this->assertSame('FAC-E1-JP-000001', $gen($empresa1->id, $juan->id));
        $this->assertSame('FAC-E1-JP-000002', $gen($empresa1->id, $juan->id));
        $this->assertSame('FAC-E1-PR-000001', $gen($empresa1->id, $pedro->id));

        // Empresa 2: same two users, independent counters again.
        $this->assertSame('FAC-E2-JP-000001', $gen($empresa2->id, $juan->id));
        $this->assertSame('FAC-E2-PR-000001', $gen($empresa2->id, $pedro->id));
        $this->assertSame('FAC-E2-PR-000002', $gen($empresa2->id, $pedro->id));

        // Four distinct (company x user) sequences exist now (plus the 2 templates).
        $this->assertSame(4, InvoiceNumberSetting::query()->whereNotNull('user_id')->count());
    }
}
