<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_fetch_profile(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret123!',
            'device_name' => 'phpunit',
        ]);

        $login->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->withHeader('Authorization', 'Bearer '.$login->json('access_token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/settings/bootstrap')->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/logout')->assertOk();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $credentials = ['email' => 'admin@example.com', 'password' => 'wrong-password'];

        // The limiter allows 5 attempts per minute per email + IP.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', $credentials)->assertStatus(422);
        }

        $this->postJson('/api/login', $credentials)->assertStatus(429);
    }
}
