<?php

namespace Tests\Feature\Services;

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
        $this->assertCount(4, $bootstrap['taxes']);
        $this->assertCount(2, $bootstrap['payment_terms']);
        $this->assertCount(3, $bootstrap['warranties']);
        $this->assertCount(2, $bootstrap['bank_accounts']);
        $this->assertCount(2, $bootstrap['fiscal_profiles']);
        $this->assertNotNull($bootstrap['invoice_number_settings']);
        $this->assertArrayHasKey('taxes', $bootstrap['settings']->toArray());
    }
}
