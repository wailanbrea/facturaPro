<?php

namespace Tests\Feature\Web;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Role;
use App\Models\Tax;
use App\Models\TechnicalReport;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_can_login_and_view_dashboard(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'admin@facturapro.local',
            'password' => 'FacturaPro123!',
        ])->assertRedirect('/');

        $this->get('/')->assertOk()->assertSee('Dashboard');
    }

    public function test_login_form_uses_relative_post_action(): void
    {
        config(['app.url' => 'https://wrong-host.example']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('action="/login"', false)
            ->assertDontSee('https://wrong-host.example/login', false);
    }

    public function test_authenticated_user_can_create_client(): void
    {
        $this->seed();
        $this->actingAs(User::query()->where('email', 'admin@facturapro.local')->firstOrFail());

        $this->post('/clients', [
            'name' => 'Cliente Web',
            'email' => 'cliente@example.com',
        ])->assertRedirect('/clients');

        $this->assertDatabaseHas('clients', ['name' => 'Cliente Web']);
    }

    public function test_user_without_permission_cannot_manage_clients(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->create());

        $this->post('/clients', [
            'name' => 'Cliente bloqueado',
            'email' => 'bloqueado@example.com',
        ])->assertForbidden();

        $this->assertDatabaseMissing('clients', ['name' => 'Cliente bloqueado']);
    }

    public function test_authenticated_user_can_create_issue_and_pay_invoice(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $client = Client::query()->create(['name' => 'Cliente Web']);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        $response = $this->post('/invoices', [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio', 'quantity' => 2, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();
        $response->assertRedirect(route('web.invoices.show', $invoice));
        $this->assertSame('236.0000', $invoice->fresh()->total);

        $this->post(route('web.invoices.issue', $invoice))->assertRedirect();
        // Number carries the fiscal profile serie (LUIS AMAURIS... => "LA").
        $this->assertSame('FAC-LA-000001', $invoice->fresh()->invoice_number);
        $this->get(route('web.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('value="236.00"', false);

        if ($this->chromeAvailable()) {
            $this->post(route('web.invoices.generate-pdf', $invoice))->assertRedirect();
            $invoice->refresh();
            $this->assertNotNull($invoice->pdf_path);
            $this->assertTrue(Storage::disk('public')->exists($invoice->pdf_path));
            $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($invoice->pdf_path));
            $this->get(route('web.invoices.show', $invoice))
                ->assertOk()
                ->assertSee('Regenerar PDF');
            $this->get(route('web.invoices.download-pdf', $invoice))->assertOk();
            Storage::disk('public')->delete($invoice->pdf_path);
        }

        $this->post(route('web.invoices.mark-paid', $invoice))->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->get(route('web.invoices.preview', $invoice))
            ->assertOk()
            ->assertSee('COBRAT');
    }

    public function test_authenticated_user_can_create_invoice_with_inline_client(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        $response = $this->post('/invoices', [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_name' => 'Cliente Directo Web',
            'client_tax_id' => '001-9999999-9',
            'client_address' => 'Santo Domingo',
            'client_phone' => '809-000-0000',
            'client_email' => 'directo@example.com',
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio', 'quantity' => 2, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ]);

        $invoice = Invoice::query()->where('client_name', 'Cliente Directo Web')->firstOrFail();

        $response->assertRedirect(route('web.invoices.show', $invoice));
        $this->assertDatabaseHas('clients', [
            'id' => $invoice->client_id,
            'name' => 'Cliente Directo Web',
            'tax_id' => '001-9999999-9',
            'email' => 'directo@example.com',
        ]);
        $this->assertSame('236.0000', $invoice->total);
    }

    public function test_invoice_logo_must_belong_to_selected_fiscal_profile(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $otherProfile = FiscalProfile::query()->create([
            'name' => 'Otra Empresa Logo',
            'tax_id' => 'B00000000',
            'address' => 'Madrid',
            'city' => 'Madrid',
            'is_active' => true,
        ]);
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        $profile->logos()->create(['path' => 'logos/empresa-a.png', 'label' => 'Empresa A']);
        $otherProfile->logos()->create(['path' => 'logos/empresa-b.png', 'label' => 'Empresa B']);

        $payload = [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_name' => 'Cliente Logo Web',
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio', 'quantity' => 1, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ];

        $this->post('/invoices', [...$payload, 'logo_path' => 'logos/empresa-b.png'])
            ->assertSessionHasErrors('logo_path');

        $this->post('/invoices', [...$payload, 'logo_path' => 'logos/empresa-a.png'])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'client_name' => 'Cliente Logo Web',
            'logo_path' => 'logos/empresa-a.png',
        ]);
    }

    public function test_authenticated_user_can_issue_and_convert_quotation_without_payment_state(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $client = Client::query()->create(['name' => 'Cliente Presupuesto']);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        $this->post('/invoices', [
            'document_type' => 'quotation',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Presupuesto servicio', 'quantity' => 2, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ])->assertRedirect();

        $quotation = Invoice::query()->where('document_type', 'quotation')->firstOrFail();

        $this->post(route('web.invoices.issue', $quotation))->assertRedirect();
        $quotation->refresh();

        $this->assertSame('PRES-LA-000001', $quotation->invoice_number);
        $this->assertSame('issued', $quotation->status);
        $this->assertSame('0.0000', $quotation->amount_received);
        $this->assertSame($quotation->total, $quotation->balance_due);

        $this->post(route('web.invoices.mark-paid', $quotation))
            ->assertRedirect()
            ->assertSessionHasErrors('invoice');

        $this->assertDatabaseMissing('invoice_payments', [
            'invoice_id' => $quotation->id,
        ]);

        $this->post(route('web.invoices.convert', $quotation))->assertRedirect();
        $quotation->refresh();
        $invoice = Invoice::query()->findOrFail($quotation->converted_to_invoice_id);

        $this->assertSame('converted', $quotation->status);
        $this->assertSame('invoice', $invoice->document_type);
        $this->assertSame($quotation->id, $invoice->source_quotation_id);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('0.0000', $invoice->amount_received);
        $this->assertSame($invoice->total, $invoice->balance_due);
    }

    public function test_technical_report_form_does_not_show_additional_texts(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $this->get(route('web.technical-reports.create'))
            ->assertOk()
            ->assertDontSee('Textos adicionales')
            ->assertDontSee('Texto introductorio')
            ->assertDontSee('Texto final')
            ->assertDontSee('Observaciones internas');
    }

    public function test_technical_report_can_be_created_with_only_first_section(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $profile = FiscalProfile::query()->firstOrFail();
        $client = Client::query()->create([
            'name' => 'Cliente Informe Web',
            'tax_id' => 'B12345678',
            'address' => 'Calle Mayor 18',
            'city' => 'Madrid',
            'is_active' => true,
        ]);

        $response = $this->post(route('web.technical-reports.store'), [
            'report_date' => '2026-07-11',
            'status' => 'draft',
            'fiscal_profile_id' => $profile->id,
            'client_id' => $client->id,
            'recipient_name' => $client->name,
            'recipient_tax_id' => $client->tax_id,
            'recipient_address' => trim($client->address.' '.$client->city),
            'section_1_title' => 'Diagnostico',
            'section_1_content' => 'Contenido principal del informe.',
        ]);

        $report = TechnicalReport::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('web.technical-reports.show', $report));
        $this->assertSame('', $report->section_2_title);
        $this->assertSame('', $report->section_3_title);
        $this->assertSame('', $report->section_4_title);
    }

    public function test_technical_report_logos_are_scoped_to_selected_fiscal_profile(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $luis = FiscalProfile::query()->where('name', 'like', 'LUIS AMAURIS%')->firstOrFail();
        $pamela = FiscalProfile::query()->where('name', 'like', 'PAMELA MISHELL%')->firstOrFail();
        $pamela->logos()->create(['path' => 'logos/pamela-only.png', 'label' => 'Pamela only']);

        $this->get(route('web.technical-reports.create'))
            ->assertOk()
            ->assertSee('data-profile-id="'.$pamela->id.'"', false)
            ->assertSee('id="report-fiscal-profile-select"', false)
            ->assertSee('refreshReportLogoVisibility', false);

        $response = $this->post(route('web.technical-reports.store'), [
            'report_date' => '2026-07-12',
            'status' => 'draft',
            'fiscal_profile_id' => $luis->id,
            'recipient_name' => 'Cliente Informe Logo',
            'recipient_address' => 'Direccion',
            'logo_path' => 'logos/pamela-only.png',
            'section_1_title' => 'Diagnostico',
            'section_1_content' => 'Contenido',
        ]);

        $response->assertSessionHasErrors('logo_path');
    }

    public function test_user_can_only_invoice_with_assigned_companies(): void
    {
        $this->seed();
        $user = User::query()->firstOrFail();
        $this->actingAs($user);

        $profiles = FiscalProfile::query()->where('is_active', true)->orderBy('id')->take(2)->get();
        $allowed = $profiles->first();
        $forbidden = $profiles->last();
        $this->assertNotSame($allowed->id, $forbidden->id);

        $user->fiscalProfiles()->sync([$allowed->id]);

        $client = Client::query()->create(['name' => 'Cliente Restringido']);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        $payload = fn (int $profileId): array => [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profileId,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio', 'quantity' => 1, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ];

        // A company the user is NOT assigned to is rejected.
        $this->post('/invoices', $payload($forbidden->id))
            ->assertSessionHasErrors('fiscal_profile_id');
        $this->assertDatabaseMissing('invoices', ['client_id' => $client->id]);

        // The assigned company is accepted.
        $this->post('/invoices', $payload($allowed->id))->assertRedirect();
        $this->assertSame($allowed->id, Invoice::query()->where('client_id', $client->id)->firstOrFail()->fiscal_profile_id);
    }

    public function test_authenticated_user_can_edit_draft_invoice(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $client = Client::query()->create(['name' => 'Cliente Web']);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();

        $this->post('/invoices', [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio inicial', 'quantity' => 1, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();

        $this->get(route('web.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('name="amount_received"', false)
            ->assertSee('value="0.00"', false)
            ->assertDontSee('value="0.0000"', false);

        $this->put(route('web.invoices.update', $invoice), [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio actualizado', 'quantity' => 3, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ])->assertRedirect(route('web.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('354.0000', $invoice->total);
        $this->assertSame('Servicio actualizado', $invoice->items()->firstOrFail()->description);
    }

    public function test_invoice_legal_texts_require_explicit_unlock_to_edit(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $client = Client::query()->create(['name' => 'Cliente Textos']);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();

        $this->post('/invoices', [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio', 'quantity' => 1, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();
        $invoice->update([
            'legal_text' => 'LEGAL ORIGINAL',
            'conformity_text' => 'CONFORMIDAD ORIGINAL',
        ]);

        $this->get(route('web.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('name="edit_legal_texts" value="0"', false)
            ->assertSee('Habilitar edicion')
            ->assertSee('data-legal-text-field readonly', false);

        $payload = [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'legal_text' => 'LEGAL CAMBIADO',
            'conformity_text' => 'CONFORMIDAD CAMBIADA',
            'items' => [
                ['description' => 'Servicio', 'quantity' => 1, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ];

        $this->put(route('web.invoices.update', $invoice), [...$payload, 'edit_legal_texts' => 0])
            ->assertRedirect(route('web.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('LEGAL ORIGINAL', $invoice->legal_text);
        $this->assertSame('CONFORMIDAD ORIGINAL', $invoice->conformity_text);

        $this->put(route('web.invoices.update', $invoice), [...$payload, 'edit_legal_texts' => 1])
            ->assertRedirect(route('web.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('LEGAL CAMBIADO', $invoice->legal_text);
        $this->assertSame('CONFORMIDAD CAMBIADA', $invoice->conformity_text);
    }

    public function test_issued_invoice_cannot_be_edited_from_web(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $client = Client::query()->create(['name' => 'Cliente Web']);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();

        $this->post('/invoices', [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 0,
            'items' => [
                ['description' => 'Servicio', 'quantity' => 1, 'unit_cost' => 100, 'tax_id' => $tax->id],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();
        $this->post(route('web.invoices.issue', $invoice));

        $this->get(route('web.invoices.edit', $invoice))
            ->assertRedirect(route('web.invoices.show', $invoice));
    }

    public function test_settings_page_renders_catalogs(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $this->get('/settings')
            ->assertOk()
            ->assertSee('Monedas')
            ->assertSee('Impuestos');

        $this->get(route('web.settings.catalog.index', 'taxes'))
            ->assertOk()
            ->assertSee('ITBIS 18%');
    }

    public function test_authenticated_user_can_manage_settings_catalogs(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $this->post(route('web.settings.catalog.store', 'taxes'), [
            'name' => 'Impuesto prueba',
            'rate' => '12.5000',
            'is_default' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('web.settings.catalog.index', 'taxes'));

        $tax = Tax::query()->where('name', 'Impuesto prueba')->firstOrFail();
        $this->assertTrue((bool) $tax->is_default);

        $this->put(route('web.settings.catalog.update', ['taxes', $tax->id]), [
            'name' => 'Impuesto prueba editado',
            'rate' => '13.0000',
            'is_default' => '0',
            'is_active' => '1',
        ])->assertRedirect(route('web.settings.catalog.index', 'taxes'));

        $this->assertDatabaseHas('taxes', ['name' => 'Impuesto prueba editado']);
    }

    public function test_fiscal_profile_logo_can_be_uploaded_from_settings(): void
    {
        Storage::fake('public');

        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $profile = FiscalProfile::query()->firstOrFail();

        $this->get(route('web.settings.catalog.edit', ['fiscal-profiles', $profile->id]))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="logo_uploads[]"', false);

        $this->put(route('web.settings.catalog.update', ['fiscal-profiles', $profile->id]), [
            'name' => $profile->name,
            'tax_id' => $profile->tax_id,
            'address' => $profile->address,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'logo_uploads' => [
                UploadedFile::fake()->image('perfil-a.png', 240, 120),
                UploadedFile::fake()->image('perfil-b.png', 240, 120),
                UploadedFile::fake()->image('perfil-c.png', 240, 120),
            ],
            'is_default' => $profile->is_default ? '1' : '0',
            'is_active' => '1',
        ])->assertRedirect(route('web.settings.catalog.index', 'fiscal-profiles'));

        $profile->refresh()->load('logos');

        $this->assertStringStartsWith('logos/', $profile->logo_path);
        $this->assertCount(3, $profile->logos);
        Storage::disk('public')->assertExists($profile->logo_path);

        $this->get(route('web.settings.catalog.edit', ['fiscal-profiles', $profile->id]))
            ->assertOk()
            ->assertSee('name="delete_logos[]"', false)
            ->assertSee('Eliminar');

        $logoToDelete = $profile->logos->first();
        $deletedPath = $logoToDelete->path;

        $this->put(route('web.settings.catalog.update', ['fiscal-profiles', $profile->id]), [
            'name' => $profile->name,
            'tax_id' => $profile->tax_id,
            'address' => $profile->address,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'delete_logos' => [$logoToDelete->id],
            'is_default' => $profile->is_default ? '1' : '0',
            'is_active' => '1',
        ])->assertRedirect(route('web.settings.catalog.index', 'fiscal-profiles'));

        $profile->refresh()->load('logos');

        $this->assertCount(2, $profile->logos);
        $this->assertNotSame($deletedPath, $profile->logo_path);
        $this->assertTrue($profile->logos->contains('path', $profile->logo_path));
        Storage::disk('public')->assertMissing($deletedPath);
    }

    public function test_authenticated_user_can_manage_users_and_roles(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());
        $role = Role::query()->where('slug', 'facturador')->firstOrFail();

        $this->post(route('web.users.store'), [
            'name' => 'Facturador Web',
            'email' => 'facturador@example.com',
            'password' => 'Password1234',
            'roles' => [$role->id],
        ])->assertRedirect(route('web.users.index'));

        $user = User::query()->where('email', 'facturador@example.com')->firstOrFail();
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
    }

    public function test_reports_page_renders_currency_grouped_totals_for_mixed_currencies(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $clienteDop = Client::query()->create(['name' => 'Cliente Reporte DOP']);
        $clienteUsd = Client::query()->create(['name' => 'Cliente Reporte USD']);

        $this->createReportInvoice([
            'client' => $clienteDop,
            'currency_code' => 'DOP',
            'invoice_date' => '2026-05-21',
            'due_date' => '2026-05-10',
            'total' => '118.0000',
            'amount_received' => '18.0000',
            'balance_due' => '100.0000',
        ]);

        $this->createReportInvoice([
            'client' => $clienteUsd,
            'currency_code' => 'USD',
            'invoice_date' => '2026-05-22',
            'due_date' => '2026-05-25',
            'total' => '50.0000',
            'amount_received' => '50.0000',
            'balance_due' => '0.0000',
            'status' => 'paid',
        ]);

        $this->get(route('web.reports.index'))
            ->assertOk()
            ->assertSee('Reportes')
            ->assertSee('Totales agrupados por moneda')
            ->assertSee('Los montos se muestran desglosados por moneda')
            ->assertSee('Cliente Reporte DOP')
            ->assertSee('Cliente Reporte USD')
            ->assertSee('Facturas vencidas')
            ->assertDontSee('Facturado consolidado');
    }

    public function test_reports_page_filters_by_currency_and_date(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $clienteDop = Client::query()->create(['name' => 'Cliente Filtro DOP']);
        $clienteUsd = Client::query()->create(['name' => 'Cliente Filtro USD']);

        $this->createReportInvoice([
            'client' => $clienteDop,
            'currency_code' => 'DOP',
            'invoice_date' => '2026-05-21',
            'due_date' => '2026-05-21',
            'total' => '236.0000',
            'amount_received' => '0.0000',
            'balance_due' => '236.0000',
        ]);

        $this->createReportInvoice([
            'client' => $clienteUsd,
            'currency_code' => 'USD',
            'invoice_date' => '2026-05-20',
            'due_date' => '2026-05-20',
            'total' => '10.0000',
            'amount_received' => '0.0000',
            'balance_due' => '10.0000',
        ]);

        $this->get(route('web.reports.index', [
            'date_from' => '2026-05-21',
            'date_to' => '2026-05-21',
            'currency_code' => 'DOP',
        ]))
            ->assertOk()
            ->assertSee('Totales consolidados')
            ->assertSee('Moneda seleccionada: DOP')
            ->assertSee('Cliente Filtro DOP')
            ->assertDontSee('Cliente Filtro USD')
            ->assertDontSee('Los montos se muestran desglosados por moneda');
    }

    public function test_invoice_preview_renders_official_template(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $client = Client::query()->create([
            'name' => 'Cliente Preview',
            'tax_id' => '001-0000000-1',
            'address' => 'Direccion extensa para validar que el bloque del cliente no rompa el layout de la factura.',
        ]);
        $currency = Currency::query()->where('code', 'DOP')->firstOrFail();
        $term = PaymentTerm::query()->where('name', 'AL CONTADO')->firstOrFail();
        $tax = Tax::query()->where('name', 'ITBIS 18%')->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $account = BankAccount::query()->firstOrFail();
        $warranty = Warranty::query()->firstOrFail();

        $this->post('/invoices', [
            'document_type' => 'invoice',
            'invoice_date' => '2026-05-21',
            'payment_term_id' => $term->id,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'fiscal_profile_id' => $profile->id,
            'bank_account_id' => $account->id,
            'warranty_id' => $warranty->id,
            'amount_received' => 50,
            'observations' => str_repeat('Observacion larga ', 16),
            'prepared_by' => 'Admin',
            'received_by' => 'Cliente',
            'items' => [
                ['description' => str_repeat('Servicio tecnico especializado ', 8), 'quantity' => 2, 'unit_cost' => 100, 'tax_id' => $tax->id],
                ['description' => 'Pieza de repuesto', 'quantity' => 1, 'unit_cost' => 25, 'tax_id' => $tax->id],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();

        $this->get(route('web.invoices.preview', $invoice))
            ->assertOk()
            ->assertSee('FACTURA')
            ->assertSee('CUENTAS BANCARIAS')
            ->assertSee('ORIGINAL: CLIENTE')
            ->assertSee('COPIA: VENDEDOR')
            ->assertSee('Cliente Preview')
            ->assertSee('RD$ 265.50')
            ->assertSee('CONFORMIDAD DEL CLIENTE');
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

    /**
     * @param array{
     *     client?: Client,
     *     currency_code?: string,
     *     invoice_date?: string,
     *     due_date?: string,
     *     total?: string,
     *     amount_received?: string,
     *     balance_due?: string,
     *     status?: string
     * } $attributes
     */
    private function createReportInvoice(array $attributes = []): Invoice
    {
        $client = $attributes['client'] ?? Client::query()->create([
            'name' => 'Cliente '.Str::upper(Str::random(5)),
        ]);
        $currency = Currency::query()->where('code', $attributes['currency_code'] ?? 'DOP')->firstOrFail();
        $invoiceDate = $attributes['invoice_date'] ?? '2026-05-21';
        $dueDate = $attributes['due_date'] ?? $invoiceDate;
        $total = $attributes['total'] ?? '100.0000';
        $amountReceived = $attributes['amount_received'] ?? '0.0000';
        $balanceDue = $attributes['balance_due'] ?? $total;

        return Invoice::query()->create([
            'invoice_number' => 'REP-'.Str::upper(Str::random(8)),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
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
            'amount_received' => $amountReceived,
            'subtotal' => $total,
            'tax_total' => '0.0000',
            'total' => $total,
            'balance_due' => $balanceDue,
            'status' => $attributes['status'] ?? 'issued',
            'created_by' => User::query()->firstOrFail()->id,
            'updated_by' => User::query()->firstOrFail()->id,
        ]);
    }
}
