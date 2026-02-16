<?php

namespace Tests\Feature\Api;

use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigGeneralApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_config_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/config/general');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'site_name',
                    'site_active',
                    'favicon_url',
                ],
            ]);
    }

    public function test_general_config_returns_default_values(): void
    {
        $response = $this->getJson('/api/config/general');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('Open Source Dashboard', $data['site_name']);
        $this->assertTrue($data['site_active']);
        $this->assertNull($data['favicon_url']);
    }

    public function test_general_config_returns_updated_site_name(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Nachhaltigkeits-Dashboard';
        $settings->save();

        $response = $this->getJson('/api/config/general');

        $response->assertStatus(200)
            ->assertJsonPath('data.site_name', 'Nachhaltigkeits-Dashboard');
    }

    public function test_general_config_returns_site_active_flag(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_active = false;
        $settings->save();

        $response = $this->getJson('/api/config/general');

        $response->assertStatus(200)
            ->assertJsonPath('data.site_active', false);
    }
}
