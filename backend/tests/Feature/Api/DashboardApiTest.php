<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::query()->firstOrFail());
    }

    public function test_dashboard_matches_service_and_excludes_quotations(): void
    {
        // Start from a clean ledger so the aggregate assertions are deterministic.
        Invoice::query()->delete();

        // One issued invoice (pending) ...
        $invoiceId = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$invoiceId}/issue")->assertOk();

        // ... one issued and fully paid ...
        $paidId = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$paidId}/issue")->assertOk();
        $this->postJson("/api/invoices/{$paidId}/mark-paid", ['payment_date' => CarbonImmutable::today()->toDateString()])
            ->assertOk();

        // ... and a quotation that must NOT count as an invoice.
        $this->createInvoice(['document_type' => 'quotation'])->json('data.id');

        $metrics = app(DashboardService::class)->metrics();

        $response = $this->getJson('/api/dashboard')->assertOk();

        // The API is a faithful projection of the shared service (web uses the same).
        $response
            ->assertJsonPath('invoice_count', $metrics['invoiceCount'])
            ->assertJsonPath('client_count', $metrics['clientCount'])
            ->assertJsonPath('pending_count', $metrics['pendingCount'])
            ->assertJsonPath('overdue_count', $metrics['overdueCount'])
            ->assertJsonPath('total_billed', number_format($metrics['totalBilled'], 2, '.', ''));

        // Two invoices exist; the quotation is excluded from the invoice count.
        $this->assertSame(2, $response->json('invoice_count'));
        $this->assertSame(1, $response->json('pending_count'));

        // Collected equals the paid invoice total; pending balance equals the unpaid one.
        $this->assertSame('236.00', $response->json('total_collected'));
        $this->assertSame('236.00', $response->json('pending_balance'));
        $this->assertSame('472.00', $response->json('total_billed'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createInvoice(array $overrides = [])
    {
        return $this->postJson('/api/invoices', $this->invoicePayload($overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function invoicePayload(array $overrides = []): array
    {
        $client = Client::query()->create([
            'name' => 'Juan Perez',
            'tax_id' => '001-1234567-8',
            'address' => 'Santo Domingo',
        ]);

        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        return array_merge([
            'document_type' => 'invoice',
            'invoice_date' => CarbonImmutable::today()->toDateString(),
            'due_date' => null,
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => '0',
            'items' => [
                ['description' => 'Servicio', 'quantity' => '1', 'unit_cost' => '200.00', 'tax_id' => $tax->id],
            ],
        ], $overrides);
    }
}
