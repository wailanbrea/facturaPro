<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = User::query()->firstOrFail();
        Sanctum::actingAs($this->user);
    }

    public function test_invoice_can_be_created_and_backend_recalculates_totals(): void
    {
        $invoice = $this->createInvoice();

        $invoice->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', '200.0000')
            ->assertJsonPath('data.tax_total', '36.0000')
            ->assertJsonPath('data.total', '236.0000')
            ->assertJsonPath('data.balance_due', '236.0000')
            ->assertJsonPath('data.items.0.line_total', '236.0000');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->json('data.id'),
            'subtotal' => '200.0000',
            'total' => '236.0000',
        ]);
    }

    public function test_selected_warranty_overrides_generic_warranty_text_from_client(): void
    {
        $warranty = Warranty::query()->where('duration_months', 6)->firstOrFail();
        $invoice = $this->createInvoice([
            'warranty_id' => $warranty->id,
            'warranty_text' => 'La garantia aplica segun las condiciones indicadas en esta factura.',
        ]);

        $invoice->assertCreated()
            ->assertJsonPath('data.warranty_id', $warranty->id)
            ->assertJsonPath('data.warranty_text', $warranty->full_text);

        $invoiceId = $invoice->json('data.id');
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'warranty_text' => $warranty->full_text,
        ]);

        $this->get("/api/invoices/{$invoiceId}/preview")
            ->assertOk()
            ->assertSee($warranty->full_text)
            ->assertDontSee('La garantia aplica segun las condiciones indicadas en esta factura.');
    }

    public function test_invoice_can_be_created_with_inline_client_payload(): void
    {
        $payload = $this->invoicePayload();
        Client::query()->whereKey($payload['client_id'])->delete();
        unset($payload['client_id']);

        $payload['client_name'] = 'Cliente Directo API';
        $payload['client_tax_id'] = '001-8888888-8';
        $payload['client_address'] = 'Santo Domingo';
        $payload['client_phone'] = '809-111-2222';
        $payload['client_email'] = 'directo-api@example.com';

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.client_name', 'Cliente Directo API')
            ->assertJsonPath('data.client_tax_id', '001-8888888-8')
            ->assertJsonPath('data.total', '236.0000');

        $this->assertDatabaseHas('clients', [
            'id' => $response->json('data.client_id'),
            'name' => 'Cliente Directo API',
            'tax_id' => '001-8888888-8',
            'email' => 'directo-api@example.com',
        ]);
    }

    public function test_invoice_index_filters_by_document_type_and_fiscal_profile(): void
    {
        $profile = FiscalProfile::query()->firstOrFail();
        $invoiceId = $this->createInvoice(['fiscal_profile_id' => $profile->id])->json('data.id');
        $quotationId = $this->createInvoice([
            'document_type' => 'quotation',
            'fiscal_profile_id' => $profile->id,
        ])->json('data.id');

        $this->getJson("/api/invoices?document_type=invoice&fiscal_profile_id={$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoiceId)
            ->assertJsonMissing(['id' => $quotationId]);

        $this->getJson("/api/invoices?document_type=quotation&fiscal_profile_id={$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $quotationId)
            ->assertJsonMissing(['id' => $invoiceId]);
    }

    public function test_inline_client_payload_reuses_existing_client_by_tax_id(): void
    {
        $existing = Client::query()->create([
            'name' => 'Cliente Existente API',
            'tax_id' => '001-7777777-7',
            'email' => 'existente@example.com',
        ]);

        $payload = $this->invoicePayload();
        Client::query()->whereKey($payload['client_id'])->delete();
        unset($payload['client_id']);

        $payload['client_name'] = 'Nombre Actualizado API';
        $payload['client_tax_id'] = '001-7777777-7';
        $payload['client_address'] = 'Direccion nueva';

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.client_id', $existing->id)
            ->assertJsonPath('data.client_name', 'Nombre Actualizado API');

        $this->assertSame(1, Client::query()->where('tax_id', '001-7777777-7')->count());
        $this->assertDatabaseHas('clients', [
            'id' => $existing->id,
            'name' => 'Nombre Actualizado API',
            'address' => 'Direccion nueva',
        ]);
    }

    public function test_invoice_logo_path_must_belong_to_selected_fiscal_profile(): void
    {
        $profile = FiscalProfile::query()->firstOrFail();
        $otherProfile = FiscalProfile::query()->create([
            'name' => 'Otra Empresa API',
            'tax_id' => 'B00000000',
            'address' => 'Madrid',
            'city' => 'Madrid',
            'is_active' => true,
        ]);

        $profile->logos()->create(['path' => 'logos/api-a.png', 'label' => 'API A']);
        $otherProfile->logos()->create(['path' => 'logos/api-b.png', 'label' => 'API B']);

        $this->postJson('/api/invoices', $this->invoicePayload([
            'fiscal_profile_id' => $profile->id,
            'logo_path' => 'logos/api-b.png',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('logo_path');

        $response = $this->postJson('/api/invoices', $this->invoicePayload([
            'fiscal_profile_id' => $profile->id,
            'logo_path' => 'logos/api-a.png',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.logo_path', 'logos/api-a.png');
    }

    public function test_manual_invoice_number_is_rejected_even_if_setting_was_enabled(): void
    {
        InvoiceNumberSetting::query()->update(['allow_manual_number' => true]);

        $response = $this->postJson('/api/invoices', $this->invoicePayload([
            'invoice_number' => 'MANUAL-001',
        ]));

        $response->assertStatus(422)
            ->assertSee('Manual invoice numbers are not allowed.');

        $this->assertDatabaseMissing('invoices', [
            'invoice_number' => 'MANUAL-001',
        ]);
    }

    public function test_invoice_draft_preview_returns_official_html_without_persisting_invoice(): void
    {
        $initialCount = Invoice::query()->count();

        $this->post('/api/invoices/preview', $this->invoicePayload([
            'currency_code' => 'EUR',
            'tax_name' => 'IVA 21%',
        ]))
            ->assertOk()
            ->assertSee('FACTURA')
            ->assertSee('BORRADOR')
            ->assertSee('Juan Perez')
            ->assertSee('242,00 EUR');

        $this->assertSame($initialCount, Invoice::query()->count());
    }

    #[DataProvider('supportedTaxesProvider')]
    public function test_invoice_creation_supports_seeded_tax_matrix(
        string $taxName,
        string $expectedTaxTotal,
        string $expectedGrandTotal,
    ): void {
        $invoice = $this->createInvoice([
            'tax_name' => $taxName,
        ]);

        $invoice->assertCreated()
            ->assertJsonPath('data.subtotal', '200.0000')
            ->assertJsonPath('data.tax_total', $expectedTaxTotal)
            ->assertJsonPath('data.total', $expectedGrandTotal)
            ->assertJsonPath('data.balance_due', $expectedGrandTotal);
    }

    #[DataProvider('supportedCurrenciesProvider')]
    public function test_invoice_creation_supports_seeded_currency_matrix(
        string $currencyCode,
        string $currencySymbol,
    ): void {
        $invoice = $this->createInvoice([
            'currency_code' => $currencyCode,
            'tax_name' => 'Sin IVA 0%',
        ]);

        $invoice->assertCreated()
            ->assertJsonPath('data.currency_code', $currencyCode)
            ->assertJsonPath('data.currency_symbol', $currencySymbol)
            ->assertJsonPath('data.total', '200.0000');
    }

    public function test_invoice_can_be_issued_and_paid(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');

        $issued = $this->postJson("/api/invoices/{$invoiceId}/issue");

        $issued->assertOk()
            ->assertJsonPath('data.invoice_number', 'FAC-LA-000001')
            ->assertJsonPath('data.status', 'issued');

        $paid = $this->postJson("/api/invoices/{$invoiceId}/mark-paid", [
            'amount' => '236',
            'payment_date' => '2026-05-21',
            'method' => 'cash',
        ]);

        $paid->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount_received', '236.0000')
            ->assertJsonPath('data.balance_due', '0.0000');

        $this->postJson("/api/invoices/{$invoiceId}/mark-paid", [
            'amount' => '1',
            'payment_date' => '2026-05-21',
            'method' => 'cash',
        ])->assertStatus(409);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoiceId,
            'amount' => '236.0000',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'invoice.issued',
            'subject_id' => $invoiceId,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'invoice.payment_recorded',
            'subject_id' => $invoiceId,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_quotation_cannot_be_created_with_received_amount(): void
    {
        $this->createInvoice([
            'document_type' => 'quotation',
            'amount_received' => '10',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_received');
    }

    public function test_quotation_issue_never_enters_payment_state(): void
    {
        $quotationId = $this->createInvoice([
            'document_type' => 'quotation',
        ])->json('data.id');

        Invoice::query()->whereKey($quotationId)->update([
            'amount_received' => '236.0000',
            'balance_due' => '0.0000',
        ]);

        $this->postJson("/api/invoices/{$quotationId}/issue")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'PRES-LA-000001')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.amount_received', '0.0000')
            ->assertJsonPath('data.balance_due', '236.0000');

        $this->postJson("/api/invoices/{$quotationId}/mark-paid", [
            'amount' => '236',
            'payment_date' => '2026-05-21',
            'method' => 'cash',
        ])->assertStatus(409);

        $this->assertDatabaseMissing('invoice_payments', [
            'invoice_id' => $quotationId,
        ]);
    }

    public function test_invoice_can_be_partially_paid(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');
        $this->postJson("/api/invoices/{$invoiceId}/issue")->assertOk();

        $this->postJson("/api/invoices/{$invoiceId}/mark-paid", [
            'amount' => '100',
            'payment_date' => '2026-05-21',
            'method' => 'transfer',
        ])->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amount_received', '100.0000')
            ->assertJsonPath('data.balance_due', '136.0000');

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoiceId,
            'amount' => '100.0000',
            'method' => 'transfer',
        ]);
    }

    public function test_past_due_invoice_is_marked_overdue_when_issued(): void
    {
        $invoiceId = $this->createInvoice([
            'invoice_date' => CarbonImmutable::yesterday()->toDateString(),
            'due_date' => CarbonImmutable::yesterday()->toDateString(),
        ])->json('data.id');

        $this->postJson("/api/invoices/{$invoiceId}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', 'overdue');
    }

    public function test_cancelled_invoice_cannot_be_updated(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');

        $this->postJson("/api/invoices/{$invoiceId}/cancel")->assertOk();

        $this->putJson("/api/invoices/{$invoiceId}", [
            'observations' => 'Should not update',
        ])->assertStatus(409);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'invoice.cancelled',
            'subject_id' => $invoiceId,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cancelled_invoice_cannot_receive_payments(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');

        $this->postJson("/api/invoices/{$invoiceId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/invoices/{$invoiceId}/mark-paid", [
            'amount' => '10',
            'payment_date' => '2026-05-21',
            'method' => 'cash',
        ])->assertStatus(409);
    }

    public function test_user_without_permission_cannot_create_invoice(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->createInvoice()->assertForbidden();
    }

    public function test_generate_and_download_pdf_endpoint(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');

        if (! $this->chromeAvailable()) {
            $this->markTestSkipped('Chrome/Chromium is required to render invoice PDFs.');
        }

        $this->postJson("/api/invoices/{$invoiceId}/generate-pdf")
            ->assertStatus(409);

        $this->postJson("/api/invoices/{$invoiceId}/issue")->assertOk();

        $generated = $this->postJson("/api/invoices/{$invoiceId}/generate-pdf")
            ->assertOk()
            ->assertJsonStructure(['pdf_path']);

        $path = $generated->json('pdf_path');
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($path));

        $this->get("/api/invoices/{$invoiceId}/download-pdf")
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'pdf_path' => $path,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'invoice.pdf_generated',
            'subject_id' => $invoiceId,
        ]);

        Storage::disk('public')->delete($path);
    }

    public function test_invoice_preview_endpoint_returns_html_template(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');

        $this->get("/api/invoices/{$invoiceId}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('FACTURA')
            ->assertSee('CUENTAS BANCARIAS')
            ->assertSee('ORIGINAL: CLIENTE')
            ->assertSee('COPIA: VENDEDOR');
    }

    public function test_quotation_preview_uses_quotation_labels(): void
    {
        $invoiceId = $this->createInvoice([
            'document_type' => 'quotation',
            'invoice_date' => '2026-06-28',
            'due_date' => '2026-06-28',
        ])->json('data.id');

        $this->get("/api/invoices/{$invoiceId}/preview")
            ->assertOk()
            ->assertSee('PRESUPUESTO')
            ->assertSee('FECHA:')
            ->assertSee('28/07/2026')
            ->assertDontSee('FECHA DE PRESUPUESTO')
            ->assertSee('PAGA Y SE&Ntilde;AL', false)
            ->assertSee('CUENTA DE')
            ->assertSee('SOMOS TECNICOS HOMOLOGOS')
            ->assertSee('RECIBIDO POR')
            ->assertSee('PREPARADO POR')
            ->assertDontSee('ORIGINAL: CLIENTE')
            ->assertDontSee('COPIA: VENDEDOR');
    }

    public function test_invoice_issue_preview_renders_as_final_invoice_without_persisting_issue(): void
    {
        $invoiceId = $this->createInvoice()->json('data.id');
        $initialNextNumber = InvoiceNumberSetting::query()->firstOrFail()->next_number;

        $this->get("/api/invoices/{$invoiceId}/issue-preview")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('FACTURA')
            ->assertSee('PROVISIONAL')
            ->assertDontSee('BORRADOR');

        $invoice = Invoice::query()->findOrFail($invoiceId);

        $this->assertSame('draft', $invoice->status);
        $this->assertNull($invoice->invoice_number);
        $this->assertSame($initialNextNumber, InvoiceNumberSetting::query()->firstOrFail()->next_number);
    }

    public function test_index_caps_per_page_to_prevent_resource_exhaustion(): void
    {
        $this->createInvoice()->assertCreated();

        $this->getJson('/api/invoices?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    /**
     * @param array{
     *     invoice_date?: string,
     *     due_date?: string,
     *     currency_code?: string,
     *     tax_name?: string,
     *     amount_received?: string,
     *     document_type?: string,
     *     invoice_number?: string
     * } $overrides
     */
    private function createInvoice(array $overrides = [])
    {
        return $this->postJson('/api/invoices', $this->invoicePayload($overrides));
    }

    /**
     * @param array{
     *     invoice_date?: string,
     *     due_date?: string,
     *     currency_code?: string,
     *     tax_name?: string,
     *     amount_received?: string,
     *     document_type?: string,
     *     invoice_number?: string
     * } $overrides
     * @return array<string, mixed>
     */
    private function invoicePayload(array $overrides = []): array
    {
        $client = Client::query()->create([
            'name' => 'Juan Perez',
            'tax_id' => '001-1234567-8',
            'address' => 'Santo Domingo',
        ]);

        $currency = Currency::query()->where('code', $overrides['currency_code'] ?? 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', $overrides['tax_name'] ?? 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        return [
            'document_type' => $overrides['document_type'] ?? 'invoice',
            'invoice_number' => $overrides['invoice_number'] ?? null,
            'invoice_date' => $overrides['invoice_date'] ?? CarbonImmutable::today()->toDateString(),
            'due_date' => $overrides['due_date'] ?? null,
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $overrides['fiscal_profile_id'] ?? $profile->id,
            'logo_path' => $overrides['logo_path'] ?? null,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => $overrides['amount_received'] ?? '0',
            'subtotal' => '999999',
            'total' => '999999',
            'items' => [
                [
                    'description' => 'Servicio tecnico',
                    'quantity' => '2',
                    'unit_cost' => '100',
                    'tax_id' => $tax->id,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function supportedTaxesProvider(): array
    {
        return [
            'iva_21' => ['IVA 21%', '42.0000', '242.0000'],
            'itbis_18' => ['ITBIS 18%', '36.0000', '236.0000'],
            'tax_7' => ['Tax 7%', '14.0000', '214.0000'],
            'exento_0' => ['Sin IVA 0%', '0.0000', '200.0000'],
        ];
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function supportedCurrenciesProvider(): array
    {
        return [
            'eur' => ['EUR', 'EUR'],
            'usd' => ['USD', 'US$'],
            'dop' => ['DOP', 'RD$'],
        ];
    }

    private function chromeAvailable(): bool
    {
        $configured = env('CHROME_PATH');

        return (is_string($configured) && $configured !== '' && is_file($configured))
            || is_file('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe')
            || is_file('C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe')
            || is_file('C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe')
            || is_file('/usr/bin/google-chrome')
            || is_file('/usr/bin/google-chrome-stable')
            || is_file('/usr/bin/chromium')
            || is_file('/usr/bin/chromium-browser');
    }
}
