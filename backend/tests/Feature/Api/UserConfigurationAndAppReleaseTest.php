<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserConfigurationAndAppReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_app_version_endpoint_returns_manifest_and_prefers_newer_uploaded_apk(): void
    {
        $response = $this->getJson('/api/app-version')->assertOk();
        $response->assertJsonStructure([
            'data' => ['version_code', 'version_name', 'download_url', 'release_notes', 'source'],
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('apk/FacturaPro-v9.9.9-99.apk', 'fake-apk-binary');

        Setting::query()->updateOrCreate(
            ['key' => 'android.app_release'],
            [
                'group' => 'mobile',
                'value' => [
                    'version_code' => 99,
                    'version_name' => '9.9.9',
                    'apk_path' => 'apk/FacturaPro-v9.9.9-99.apk',
                    'release_notes' => 'Prueba de APK subida al servidor',
                    'published_at' => '2026-11-24',
                ],
            ],
        );

        $updated = $this->getJson('/api/app-version')->assertOk();
        $updated->assertJsonPath('data.version_code', 99)
            ->assertJsonPath('data.version_name', '9.9.9')
            ->assertJsonPath('data.source', 'server');

        $this->get('/api/app-version/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive');
    }

    public function test_calendar_role_only_sees_own_user_and_calendar_configuration(): void
    {
        $calendarRole = Role::query()->where('slug', 'operador')->firstOrFail();
        $user = User::factory()->create(['name' => 'Operador Citas']);
        $user->roles()->sync([$calendarRole->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user-configurations')->assertOk();
        $response->assertJsonPath('data.current_user.name', 'Operador Citas')
            ->assertJsonPath('data.current_user.roles.0.slug', 'operador')
            ->assertJsonPath('data.calendar_config.can_view_calendar', true)
            ->assertJsonPath('data.invoicing_config', null)
            ->assertJsonPath('data.technical_reports_config', null)
            ->assertJsonPath('data.system_users_config', null);
    }

    public function test_facturador_role_sees_operational_configs_without_other_users(): void
    {
        $facturadorRole = Role::query()->where('slug', 'facturador')->firstOrFail();
        $user = User::factory()->create(['name' => 'Tecnico Facturador']);
        $user->roles()->sync([$facturadorRole->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user-configurations')->assertOk();
        $response->assertJsonPath('data.current_user.name', 'Tecnico Facturador')
            ->assertJsonPath('data.current_user.roles.0.slug', 'facturador')
            ->assertJsonPath('data.system_users_config', null);

        $this->assertNotNull($response->json('data.invoicing_config'));
        $this->assertNotNull($response->json('data.technical_reports_config'));
        $this->assertNotEmpty($response->json('data.current_user.assigned_companies'));
    }

    public function test_admin_role_sees_all_users_roles_and_system_configurations(): void
    {
        $admin = User::query()->where('email', 'admin@facturapro.local')->firstOrFail();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/user-configurations')->assertOk();
        $this->assertNotNull($response->json('data.invoicing_config'));
        $this->assertNotNull($response->json('data.technical_reports_config'));
        $this->assertNotNull($response->json('data.system_users_config'));
        $this->assertNotEmpty($response->json('data.system_users_config.users'));
        $this->assertNotEmpty($response->json('data.system_users_config.roles'));
    }
}
