<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_returns_seeded_settings(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->getJson('/api/settings/bootstrap')
            ->assertOk()
            ->assertJsonCount(1, 'data.currencies')
            ->assertJsonCount(2, 'data.taxes')
            ->assertJsonCount(2, 'data.payment_terms')
            ->assertJsonCount(3, 'data.warranties');
    }

    public function test_individual_settings_endpoints_return_active_catalogs(): void
    {
        $this->seed();
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->getJson('/api/currencies')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/taxes')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/payment-terms')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/warranties')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/bank-accounts')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/fiscal-profiles')->assertOk()->assertJsonCount(2, 'data');
    }
}
