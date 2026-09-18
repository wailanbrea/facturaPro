<?php

namespace Tests\Feature\Services;

use App\Models\Role;
use App\Models\User;
use App\Services\SettingsBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_configuration_for_clients(): void
    {
        $this->seed();

        $bootstrap = app(SettingsBootstrapService::class)->get();

        $this->assertCount(1, $bootstrap['currencies']);
        $this->assertCount(2, $bootstrap['taxes']);
        $this->assertCount(2, $bootstrap['payment_terms']);
        $this->assertCount(3, $bootstrap['warranties']);
        $this->assertCount(2, $bootstrap['bank_accounts']);
        $this->assertCount(2, $bootstrap['fiscal_profiles']);
        $this->assertNotNull($bootstrap['invoice_number_settings']);
        $this->assertSame(['conformity_text', 'legal_text'], $bootstrap['invoice_locked_fields']);
        $this->assertArrayHasKey('taxes', $bootstrap['settings']->toArray());
    }

    public function test_facturador_can_edit_all_invoice_text_fields(): void
    {
        $this->seed();
        $facturador = User::factory()->create();
        $facturador->roles()->attach(Role::query()->where('slug', 'facturador')->firstOrFail());
        $this->actingAs($facturador);

        $bootstrap = app(SettingsBootstrapService::class)->get();

        $this->assertSame([], $bootstrap['invoice_locked_fields']);
    }
}
