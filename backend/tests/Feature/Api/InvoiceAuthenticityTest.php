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
use App\Services\InvoiceSignatureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceAuthenticityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::query()->firstOrFail());
    }

    public function test_issuing_an_invoice_seals_it_with_a_signature(): void
    {
        $id = $this->createInvoice()->json('data.id');

        $this->postJson("/api/invoices/{$id}/issue")->assertOk();

        $invoice = Invoice::query()->findOrFail($id);

        $this->assertNotNull($invoice->verification_hash);
        $this->assertNotNull($invoice->verification_code);
        $this->assertNotNull($invoice->signed_at);
        $this->assertNotNull($invoice->previous_hash);
        $this->assertTrue(app(InvoiceSignatureService::class)->matches($invoice));
    }

    public function test_verify_endpoint_confirms_a_genuine_invoice(): void
    {
        $id = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$id}/issue")->assertOk();
        $invoice = Invoice::query()->findOrFail($id);

        $this->getJson('/api/invoices/verify?number='.urlencode($invoice->invoice_number).'&code='.$invoice->verification_code)
            ->assertOk()
            ->assertJsonPath('status', 'authentic')
            ->assertJsonPath('authentic', true)
            ->assertJsonPath('invoice.invoice_number', $invoice->invoice_number);
    }

    public function test_verify_endpoint_rejects_a_forged_or_unknown_invoice(): void
    {
        $this->getJson('/api/invoices/verify?number=FAC-999999&code=DEAD-BEEF-DEAD-BEEF')
            ->assertOk()
            ->assertJsonPath('status', 'not_found')
            ->assertJsonPath('authentic', false);
    }

    public function test_verify_endpoint_detects_tampered_data(): void
    {
        $id = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$id}/issue")->assertOk();
        $invoice = Invoice::query()->findOrFail($id);

        // Simulate a direct database tamper of the total.
        Invoice::query()->where('id', $id)->update(['total' => '999999.0000']);

        $this->getJson('/api/invoices/verify?number='.urlencode($invoice->invoice_number).'&code='.$invoice->verification_code)
            ->assertOk()
            ->assertJsonPath('status', 'altered')
            ->assertJsonPath('authentic', false);
    }

    public function test_signed_invoice_cannot_modify_authenticated_fields(): void
    {
        $id = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$id}/issue")->assertOk();

        $this->putJson("/api/invoices/{$id}", ['amount_received' => '50.00'])
            ->assertStatus(409);
    }

    public function test_chain_links_consecutive_invoices_and_audit_detects_tampering(): void
    {
        $signature = app(InvoiceSignatureService::class);

        $firstId = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$firstId}/issue")->assertOk();
        $first = Invoice::query()->findOrFail($firstId);

        $secondId = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$secondId}/issue")->assertOk();
        $second = Invoice::query()->findOrFail($secondId);

        // The second invoice's chain link points at the first's signature.
        $this->assertSame($first->verification_hash, $second->previous_hash);

        // A genuine chain passes the audit command.
        $this->artisan('invoices:verify-chain')->assertExitCode(0);

        // Tampering a record breaks the audit.
        Invoice::query()->where('id', $firstId)->update(['total' => '123456.0000']);
        $this->artisan('invoices:verify-chain')->assertExitCode(1);
    }

    public function test_backfill_command_signs_pre_existing_issued_invoices(): void
    {
        // Create an issued invoice the "legacy" way: a number but no signature.
        $id = $this->createInvoice()->json('data.id');
        Invoice::query()->where('id', $id)->update([
            'status' => 'issued',
            'invoice_number' => 'LEGACY-0001',
            'verification_hash' => null,
            'verification_code' => null,
            'previous_hash' => null,
            'signed_at' => null,
        ]);

        $this->artisan('invoices:sign-existing')->assertExitCode(0);

        $invoice = Invoice::query()->findOrFail($id);
        $this->assertNotNull($invoice->verification_hash);
        $this->assertTrue(app(InvoiceSignatureService::class)->matches($invoice));
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
                ['description' => 'Servicio', 'quantity' => '1', 'unit_cost' => '100.00', 'tax_id' => $tax->id],
            ],
        ], $overrides);
    }
}
