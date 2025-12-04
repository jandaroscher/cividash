<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingConfigApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_get_branding_config_returns_all_fields(): void
    {
        $response = $this->getJson('/api/config/branding');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'primary_color',
                    'secondary_color',
                    'logo_url',
                    'accent_color',
                    'typography_font_family',
                    'typography_font_weights',
                    'typography_font_sizes',
                    'typography_custom_font_name',
                    'typography_custom_font_file',
                    'slider_colors' => [
                        'rail',
                        'handle',
                        'handleBorder',
                    ],
                    'header_background_color',
                    'footer_background_color',
                ],
            ]);
    }

    public function test_get_branding_config_returns_default_values(): void
    {
        $response = $this->getJson('/api/config/branding');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('#0d47a1', $data['primary_color']);
        $this->assertEquals('#1976d2', $data['secondary_color']);
        $this->assertEquals('Open Sans', $data['typography_font_family']);
        $this->assertIsArray($data['typography_font_weights']);
        $this->assertIsArray($data['slider_colors']);
        $this->assertIsArray($data['typography_font_sizes']);
        $this->assertNull($data['typography_custom_font_name']);
        $this->assertNull($data['typography_custom_font_file']);
        $this->assertEquals('#FFFFFF', $data['header_background_color']);
        $this->assertEquals('#E5E7EB', $data['footer_background_color']);
    }

    public function test_post_branding_config_requires_authentication(): void
    {
        $response = $this->postJson('/api/admin/config/branding', [
            'primary_color' => '#FF0000',
        ]);

        $response->assertStatus(401);
    }

    public function test_post_branding_config_updates_settings(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'accent_color' => '#0000FF',
                'typography_font_family' => 'Roboto',
                'typography_font_weights' => [400, 500, 700],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'primary_color' => '#FF0000',
                    'secondary_color' => '#00FF00',
                    'accent_color' => '#0000FF',
                    'typography_font_family' => 'Roboto',
                    'typography_font_weights' => [400, 500, 700],
                ],
            ]);

        // Verify settings were saved
        $settings = app(BrandingSettings::class);
        $this->assertEquals('#FF0000', $settings->primary_color);
        $this->assertEquals('#00FF00', $settings->secondary_color);
        $this->assertEquals('#0000FF', $settings->accent_color);
        $this->assertEquals('Roboto', $settings->typography_font_family);
        $this->assertEquals([400, 500, 700], $settings->typography_font_weights);
    }

    public function test_patch_branding_config_partially_updates_settings(): void
    {
        $user = User::factory()->create();
        
        // Set initial values
        $settings = app(BrandingSettings::class);
        $settings->primary_color = '#000000';
        $settings->secondary_color = '#FFFFFF';
        $settings->save();

        // Update only primary_color
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/admin/config/branding', [
                'primary_color' => '#FF0000',
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('#FF0000', $data['primary_color']);
        // secondary_color should remain unchanged
        $this->assertEquals('#FFFFFF', $data['secondary_color']);

        // Verify settings
        $settings = app(BrandingSettings::class);
        $this->assertEquals('#FF0000', $settings->primary_color);
        $this->assertEquals('#FFFFFF', $settings->secondary_color);
    }

    public function test_post_branding_config_validates_color_format(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'primary_color' => 'invalid-color',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_post_branding_config_validates_hex_color_format(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#GGG',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_post_branding_config_accepts_valid_hex_colors(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#ABC',
                'secondary_color' => '#ABCDEF',
            ]);

        $response->assertStatus(200);
    }

    public function test_post_branding_config_validates_font_weights(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'typography_font_weights' => [50, 1500], // Invalid weights
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['typography_font_weights.0', 'typography_font_weights.1']);
    }

    public function test_post_branding_config_updates_slider_colors(): void
    {
        $user = User::factory()->create();
        
        $sliderColors = [
            'rail' => '#000000',
            'handle' => '#FFFFFF',
            'handleBorder' => '#CCCCCC',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'slider_colors' => $sliderColors,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('#000000', $data['slider_colors']['rail']);
        $this->assertEquals('#FFFFFF', $data['slider_colors']['handle']);
        $this->assertEquals('#CCCCCC', $data['slider_colors']['handleBorder']);

        $settings = app(BrandingSettings::class);
        $this->assertEquals($sliderColors, $settings->slider_colors);
    }

    public function test_post_branding_config_validates_slider_color_format(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'slider_colors' => [
                    'rail' => 'invalid-color',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slider_colors.rail']);
    }

    public function test_get_branding_config_after_update_returns_updated_values(): void
    {
        $user = User::factory()->create();
        
        // Update settings
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#123456',
                'typography_font_family' => 'Inter',
            ]);

        // Get settings
        $response = $this->getJson('/api/config/branding');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('#123456', $data['primary_color']);
        $this->assertEquals('Inter', $data['typography_font_family']);
    }

    public function test_post_branding_config_updates_header_footer_colors(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'header_background_color' => '#F0F0F0',
                'footer_background_color' => '#CCCCCC',
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('#F0F0F0', $data['header_background_color']);
        $this->assertEquals('#CCCCCC', $data['footer_background_color']);

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#F0F0F0', $settings->header_background_color);
        $this->assertEquals('#CCCCCC', $settings->footer_background_color);
    }

    public function test_post_branding_config_validates_header_footer_color_format(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/config/branding', [
                'header_background_color' => 'invalid-color',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['header_background_color']);
    }
}

