<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::query()->firstOrFail());
    }

    public function test_appointment_can_be_created_with_android_payload(): void
    {
        $this->postJson('/api/appointments', [
            'title' => 'Revision en sitio',
            'start_at' => '2026-07-18T09:00',
            'end_at' => '2026-07-18T10:00',
            'location' => 'Santo Domingo, Republica Dominicana',
            'location_lat' => 18.4861,
            'location_lng' => -69.9312,
            'service_description' => 'Revision tecnica',
            'observations' => 'Llamar antes de llegar',
            'contacts' => [['name' => 'Contacto', 'phone' => '8095550101']],
            'status' => 'pending',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Revision en sitio')
            ->assertJsonPath('data.location_lat', 18.4861)
            ->assertJsonPath('data.location_lng', -69.9312)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('appointments', [
            'title' => 'Revision en sitio',
            'location' => 'Santo Domingo, Republica Dominicana',
        ]);
    }

    public function test_appointment_rejects_an_end_time_not_after_start_time(): void
    {
        $this->postJson('/api/appointments', [
            'title' => 'Cita invalida',
            'start_at' => '2026-07-18T10:00',
            'end_at' => '2026-07-18T10:00',
            'status' => 'pending',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_at');
    }
}
