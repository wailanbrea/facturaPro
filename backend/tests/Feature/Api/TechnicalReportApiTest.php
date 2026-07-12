<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\FiscalProfile;
use App\Models\ReportSetting;
use App\Models\TechnicalReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicalReportApiTest extends TestCase
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

    public function test_report_settings_endpoint_returns_default_configuration(): void
    {
        $this->getJson('/api/report-settings')
            ->assertOk()
            ->assertJsonPath('data.section_1_default_title', 'Diagnóstico de la Avería')
            ->assertJsonPath('data.report_prefix', 'INF-')
            ->assertJsonPath('data.next_number_preview', 'INF-000001');
    }

    public function test_technical_report_can_be_created_and_keeps_section_title_snapshot(): void
    {
        $created = $this->postJson('/api/technical-reports', $this->reportPayload())
            ->assertCreated()
            ->assertJsonPath('data.report_number', 'INF-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.section_1_title', 'Diagnóstico de la Avería');

        ReportSetting::query()->firstOrFail()->update([
            'section_1_default_title' => 'Nuevo titulo global',
        ]);

        $this->assertDatabaseHas('technical_reports', [
            'id' => $created->json('data.id'),
            'section_1_title' => 'Diagnóstico de la Avería',
        ]);

        $second = $this->postJson('/api/technical-reports', $this->reportPayload([
            'section_1_title' => ReportSetting::current()->section_1_default_title,
        ]))->assertCreated();

        $second->assertJsonPath('data.report_number', 'INF-000002')
            ->assertJsonPath('data.section_1_title', 'Nuevo titulo global');
    }

    public function test_issued_technical_report_is_signed_and_can_be_verified(): void
    {
        $created = $this->postJson('/api/technical-reports', $this->reportPayload([
            'status' => 'issued',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'issued');

        $report = TechnicalReport::query()->findOrFail($created->json('data.id'));

        $this->assertNotNull($report->verification_hash);
        $this->assertNotNull($report->verification_code);
        $this->assertNotNull($report->signed_at);

        $this->getJson('/api/invoices/verify?number='.urlencode($report->report_number).'&code='.$report->verification_code)
            ->assertOk()
            ->assertJsonPath('type', 'report')
            ->assertJsonPath('authentic', true)
            ->assertJsonPath('report.report_number', $report->report_number);

        $this->get('/invoices/verify?number='.urlencode($report->report_number).'&code='.$report->verification_code)
            ->assertOk()
            ->assertSee('Documento autentico');

        $this->putJson("/api/technical-reports/{$report->id}", $this->reportPayload([
            'section_1_content' => 'Intento de cambio posterior a la firma.',
        ]))->assertStatus(409);
    }

    public function test_manual_report_number_is_rejected_even_if_setting_was_enabled(): void
    {
        ReportSetting::current()->update(['allow_manual_number' => true]);

        $this->postJson('/api/technical-reports', $this->reportPayload([
            'report_number' => 'MANUAL-INF-001',
        ]))->assertStatus(422)
            ->assertSee('La numeracion manual de informes no esta permitida.');

        $this->assertDatabaseMissing('technical_reports', [
            'report_number' => 'MANUAL-INF-001',
        ]);
    }

    public function test_report_preview_uses_independent_report_template(): void
    {
        $reportId = $this->postJson('/api/technical-reports', $this->reportPayload())->json('data.id');

        $this->get("/api/technical-reports/{$reportId}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('INFORME')
            ->assertSee('Diagnóstico de la Avería')
            ->assertSee('Cliente Informe')
            ->assertDontSee('TOTAL A PAGAR')
            ->assertDontSee('CUENTAS BANCARIAS');
    }

    public function test_generate_and_download_report_pdf_endpoint(): void
    {
        if (! $this->chromeAvailable()) {
            $this->markTestSkipped('Chrome/Chromium is required to render report PDFs.');
        }

        $reportId = $this->postJson('/api/technical-reports', $this->reportPayload())->json('data.id');

        $generated = $this->postJson("/api/technical-reports/{$reportId}/generate-pdf")
            ->assertOk()
            ->assertJsonStructure(['pdf_path']);

        $path = $generated->json('pdf_path');
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($path));

        $this->get("/api/technical-reports/{$reportId}/download-pdf")
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('technical_reports', [
            'id' => $reportId,
            'status' => 'issued',
            'pdf_path' => $path,
        ]);

        $report = TechnicalReport::query()->findOrFail($reportId);
        $this->assertNotNull($report->verification_hash);
        $this->assertNotNull($report->verification_code);
        $this->assertNotNull($report->pdf_sha256);

        $this->getJson('/api/invoices/verify?number='.urlencode($report->report_number).'&code='.$report->verification_code)
            ->assertOk()
            ->assertJsonPath('type', 'report')
            ->assertJsonPath('authentic', true)
            ->assertJsonPath('report.report_number', $report->report_number);

        Storage::disk('public')->delete($path);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function reportPayload(array $overrides = []): array
    {
        $client = Client::query()->firstOrCreate(
            ['email' => 'cliente.informe@example.com'],
            [
                'name' => 'Cliente Informe',
                'tax_id' => 'B12345678',
                'address' => 'Carrer Muntaner 75 Primer Piso',
                'city' => 'Barcelona',
                'is_active' => true,
            ],
        );
        $profile = FiscalProfile::query()->firstOrFail();
        $setting = ReportSetting::current();

        return [
            'report_date' => '2026-03-04',
            'fiscal_profile_id' => $profile->id,
            'client_id' => $client->id,
            'recipient_name' => $client->name,
            'recipient_tax_id' => $client->tax_id,
            'recipient_address' => trim($client->address.' '.$client->city),
            'section_1_title' => $setting->section_1_default_title,
            'section_1_content' => 'Se detecta averia en el sistema tecnico informado por el cliente.',
            'section_2_title' => $setting->section_2_default_title,
            'section_2_content' => 'Se realizan pruebas, limpieza y ajuste de componentes.',
            'section_3_title' => $setting->section_3_default_title,
            'section_3_content' => 'Los resultados posteriores quedan dentro de parametros aceptables.',
            'section_4_title' => $setting->section_4_default_title,
            'section_4_content' => 'El equipo queda operativo tras la intervencion realizada.',
            'status' => 'draft',
            ...$overrides,
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
