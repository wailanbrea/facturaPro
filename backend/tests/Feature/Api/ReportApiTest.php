<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@facturapro.local')->firstOrFail());
    }

    public function test_reports_endpoint_returns_currency_grouped_operational_summary(): void
    {
        $dopClient = Client::query()->create(['name' => 'Cliente API DOP']);
        $usdClient = Client::query()->create(['name' => 'Cliente API USD']);

        $this->createReportInvoice($dopClient, 'DOP', [
            'invoice_date' => CarbonImmutable::yesterday()->toDateString(),
            'due_date' => CarbonImmutable::yesterday()->toDateString(),
            'total' => '236.0000',
            'amount_received' => '100.0000',
            'balance_due' => '136.0000',
            'status' => 'issued',
        ]);
        $this->createReportInvoice($usdClient, 'USD', [
            'invoice_date' => CarbonImmutable::today()->toDateString(),
            'due_date' => CarbonImmutable::tomorrow()->toDateString(),
            'total' => '50.0000',
            'amount_received' => '50.0000',
            'balance_due' => '0.0000',
            'status' => 'paid',
        ]);

        $this->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('data.overview.invoices_count', 2)
            ->assertJsonPath('data.overview.overdue_count', 1)
            ->assertJsonPath('data.can_show_unified_money_totals', false)
            ->assertJsonPath('data.totals', null)
            ->assertJsonPath('data.totals_by_currency.0.currency_code', 'DOP')
            ->assertJsonPath('data.totals_by_currency.1.currency_code', 'USD')
            ->assertJsonPath('data.overdue_invoices.0.client_name', 'Cliente API DOP');
    }

    public function test_reports_endpoint_filters_by_currency_and_date(): void
    {
        $dopClient = Client::query()->create(['name' => 'Cliente Filtro API DOP']);
        $usdClient = Client::query()->create(['name' => 'Cliente Filtro API USD']);

        $this->createReportInvoice($dopClient, 'DOP', [
            'invoice_date' => '2026-05-21',
            'due_date' => '2026-05-21',
            'total' => '236.0000',
            'balance_due' => '236.0000',
        ]);
        $this->createReportInvoice($usdClient, 'USD', [
            'invoice_date' => '2026-05-20',
            'due_date' => '2026-05-20',
            'total' => '99.0000',
            'balance_due' => '99.0000',
        ]);

        $this->getJson('/api/reports?date_from=2026-05-21&date_to=2026-05-21&currency_code=DOP')
            ->assertOk()
            ->assertJsonPath('data.can_show_unified_money_totals', true)
            ->assertJsonPath('data.totals.currency_code', 'DOP')
            ->assertJsonPath('data.totals.total_invoiced', '236.0000')
            ->assertJsonCount(1, 'data.totals_by_currency')
            ->assertJsonPath('data.by_client.0.client_name', 'Cliente Filtro API DOP');
    }

    public function test_financial_reports_exclude_quotations(): void
    {
        $client = Client::query()->create(['name' => 'Cliente Presupuesto API']);

        $this->createReportInvoice($client, 'DOP', [
            'document_type' => 'quotation',
            'invoice_date' => '2026-05-21',
            'due_date' => '2026-05-21',
            'total' => '999.0000',
            'balance_due' => '999.0000',
            'status' => 'issued',
        ]);

        $this->getJson('/api/reports?date_from=2026-05-21&date_to=2026-05-21&currency_code=DOP')
            ->assertOk()
            ->assertJsonPath('data.overview.invoices_count', 0)
            ->assertJsonCount(0, 'data.totals_by_currency')
            ->assertJsonCount(0, 'data.by_client');
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function createReportInvoice(Client $client, string $currencyCode, array $overrides): Invoice
    {
        $currency = Currency::query()->where('code', $currencyCode)->firstOrFail();

        return Invoice::query()->create([
            'invoice_number' => $overrides['invoice_number'] ?? null,
            'document_type' => $overrides['document_type'] ?? 'invoice',
            'invoice_date' => $overrides['invoice_date'],
            'due_date' => $overrides['due_date'],
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_tax_id' => $client->tax_id,
            'client_address' => $client->address,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
            'amount_received' => $overrides['amount_received'] ?? '0.0000',
            'subtotal' => $overrides['total'],
            'tax_total' => '0.0000',
            'total' => $overrides['total'],
            'balance_due' => $overrides['balance_due'],
            'status' => $overrides['status'] ?? 'issued',
        ]);
    }
}
