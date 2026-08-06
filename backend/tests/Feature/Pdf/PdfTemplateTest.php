<?php

namespace Tests\Feature\Pdf;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the PDF templates: layout invariants that are easy to break by
 * accident and expensive to notice, because they only show up in the rendered
 * document.
 */
class PdfTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->actingAs(User::query()->firstOrFail());
    }

    public function test_templates_never_reference_remote_resources(): void
    {
        $html = $this->previewHtml($this->makeInvoice(3));

        // A remote font or CDN would make PDF generation depend on the network
        // at render time, and fail silently on an offline server.
        $this->assertStringNotContainsString('https://fonts.', $html);
        $this->assertStringNotContainsString('cdn.', $html);
        $this->assertStringNotContainsString('<script', $html);

        preg_match_all('/\ssrc="([^"]+)"/', $html, $matches);
        foreach ($matches[1] as $src) {
            $this->assertStringStartsWith('data:', $src, "El recurso {$src} no esta embebido.");
        }
    }

    public function test_templates_contain_no_decorative_unicode_glyphs(): void
    {
        $html = $this->previewHtml($this->makeInvoice(3));

        // Emoji and dingbats render differently depending on the system fonts,
        // so the templates use inline SVG instead.
        $found = preg_match_all(
            '/[\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{2500}-\x{27BF}\x{2B00}-\x{2BFF}\x{1F000}-\x{1FAFF}]/u',
            $html,
        );

        $this->assertSame(0, $found, 'La plantilla no debe contener glifos Unicode decorativos.');
    }

    public function test_forbidden_blocks_are_absent(): void
    {
        $html = $this->previewHtml($this->makeInvoice(3));

        foreach (['Bizum', 'Efectivo', 'Categor', 'ORIGINAL: CLIENTE', 'COPIA: VENDEDOR'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }

        // El telefono del emisor no debe salir en la tarjeta fiscal.
        $profile = FiscalProfile::query()->firstOrFail();
        $profile->update(['phone' => '+34 900 111 222']);

        $this->assertStringNotContainsString('+34 900 111 222', $this->previewHtml($this->makeInvoice(3)));
    }

    public function test_a_short_invoice_fits_in_two_sheets(): void
    {
        $html = $this->previewHtml($this->makeInvoice(3));

        $this->assertSame(2, substr_count($html, 'class="sheet"'));
        $this->assertStringContainsString('GINA 1 DE 2', $html);
    }

    public function test_a_long_invoice_spills_into_continuation_sheets_without_losing_lines(): void
    {
        $invoice = $this->makeInvoice(40);
        $html = $this->previewHtml($invoice);

        $sheets = substr_count($html, 'class="sheet"');
        $this->assertGreaterThan(2, $sheets, 'Cuarenta lineas no caben en dos hojas.');
        $this->assertStringContainsString("GINA 1 DE {$sheets}", $html);

        // Ninguna linea puede desaparecer en silencio.
        foreach ($invoice->items as $item) {
            $this->assertStringContainsString($item->description, $html);
        }
    }

    public function test_the_legal_conditions_are_always_on_the_last_sheet(): void
    {
        $html = $this->previewHtml($this->makeInvoice(40));

        $lastItemsTable = strrpos($html, 'class="items"');
        $legalGrid = strrpos($html, 'class="legal-grid"');

        $this->assertNotFalse($legalGrid);
        $this->assertGreaterThan($lastItemsTable, $legalGrid);
    }

    public function test_a_quotation_uses_its_own_template(): void
    {
        $html = $this->previewHtml($this->makeInvoice(3, 'quotation'));

        $this->assertStringContainsString('PRESUPUESTO', $html);
        $this->assertStringContainsString('Detalle de los trabajos', $html);
        $this->assertStringNotContainsString('Actuaciones', $html);
    }

    private function previewHtml(Invoice $invoice): string
    {
        return $this->get(route('web.invoices.preview', $invoice))
            ->assertOk()
            ->getContent();
    }

    private function makeInvoice(int $lines, string $documentType = 'invoice'): Invoice
    {
        $currency = Currency::query()->where('code', 'EUR')->firstOrFail();
        $tax = Tax::query()->where('name', 'IVA 21%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $client = Client::query()->create(['name' => 'Cliente PDF']);

        $invoice = Invoice::query()->create([
            'document_type' => $documentType,
            'invoice_date' => '2026-08-05',
            'due_date' => '2026-09-04',
            'payment_term_id' => PaymentTerm::query()->firstOrFail()->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
            'fiscal_profile_id' => $profile->id,
            'seller_name' => $profile->name,
            'seller_city' => $profile->city,
            'bank_account_id' => BankAccount::query()->firstOrFail()->id,
            'warranty_id' => Warranty::query()->firstOrFail()->id,
            'subtotal' => '100.0000',
            'taxable_base' => '100.0000',
            'tax_total' => '21.0000',
            'total' => '121.0000',
            'balance_due' => '121.0000',
            'status' => 'draft',
        ]);

        for ($i = 0; $i < $lines; $i++) {
            $invoice->items()->create([
                'description' => sprintf('LINEA-%03d concepto facturable', $i),
                'quantity' => '1.0000',
                'unit_cost' => '10.0000',
                'tax_id' => $tax->id,
                'tax_name' => $tax->name,
                'tax_rate' => '21.0000',
                'tax_amount' => '2.1000',
                'unit_price' => '12.1000',
                'line_subtotal' => '10.0000',
                'line_total' => '12.1000',
                'sort_order' => $i,
            ]);
        }

        return $invoice->fresh();
    }
}
