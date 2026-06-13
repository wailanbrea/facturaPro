<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@facturapro.local')->firstOrFail());
    }

    public function test_client_crud_flow(): void
    {
        $created = $this->postJson('/api/clients', [
            'name' => 'Acme Corp',
            'tax_id' => '001-1234567-8',
            'email' => 'billing@example.com',
            'address' => 'Santo Domingo',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Acme Corp');

        $clientId = $created->json('data.id');

        $this->getJson("/api/clients/{$clientId}")
            ->assertOk()
            ->assertJsonPath('data.email', 'billing@example.com');

        $this->putJson("/api/clients/{$clientId}", [
            'name' => 'Acme Updated',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Acme Updated')
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/clients?search=Updated')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Acme Updated');

        $this->deleteJson("/api/clients/{$clientId}")->assertNoContent();
        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
    }

    public function test_client_with_invoices_is_deactivated_instead_of_deleted(): void
    {
        $client = Client::query()->create(['name' => 'Cliente con factura']);

        $currency = \App\Models\Currency::query()->where('code', 'DOP')->firstOrFail();
        \App\Models\Invoice::query()->create([
            'invoice_date' => '2026-05-21',
            'client_id' => $client->id,
            'client_name' => $client->name,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
        ]);

        $this->deleteJson("/api/clients/{$client->id}")->assertNoContent();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'is_active' => false]);
    }
}
