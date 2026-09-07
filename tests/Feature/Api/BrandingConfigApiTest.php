<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Settings\BrandingSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingConfigApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Create tenant and user for Admin API tests
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Create a Sanctum token with tenant_id for API requests.
     */
    protected function createTokenForTenant(Tenant $tenant, array $abilities = ['admin-api']): string
    {
        $token = $this->user->createToken('test-token', $abilities);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    // ========== Public GET Tests (no auth required) ==========

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

    // ========== Auth Tests ==========

    public function test_post_branding_config_requires_authentication(): void
    {
        $response = $this->postJson('/api/admin/config/branding', [
            'primary_color' => '#FF0000',
        ]);

        $response->assertStatus(401);
    }

    // ========== POST/PATCH Tests with Token-Tenant Auth ==========

    public function test_post_branding_config_updates_settings(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
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
        $token = $this->createTokenForTenant($this->tenant);

        // Set initial values
        $settings = app(BrandingSettings::class);
        $settings->primary_color = '#000000';
        $settings->secondary_color = '#FFFFFF';
        $settings->save();

        // Update only primary_color
        $response = $this->withHeader('Authorization', "Bearer {$token}")
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
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'primary_color' => 'invalid-color',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_post_branding_config_validates_hex_color_format(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#GGG',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_post_branding_config_accepts_valid_hex_colors(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#ABC',
                'secondary_color' => '#ABCDEF',
            ]);

        $response->assertStatus(200);
    }

    public function test_post_branding_config_validates_font_weights(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'typography_font_weights' => [50, 1500], // Invalid weights
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['typography_font_weights.0', 'typography_font_weights.1']);
    }

    public function test_post_branding_config_updates_slider_colors(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $sliderColors = [
            'rail' => '#000000',
            'handle' => '#FFFFFF',
            'handleBorder' => '#CCCCCC',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
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
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
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
        $token = $this->createTokenForTenant($this->tenant);

        // Update settings
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#123456',
                'typography_font_family' => 'Inter',
            ]);

        // Get settings (public endpoint, no auth required)
        $response = $this->getJson('/api/config/branding');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('#123456', $data['primary_color']);
        $this->assertEquals('Inter', $data['typography_font_family']);
    }

    public function test_post_branding_config_updates_header_footer_colors(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
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
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'header_background_color' => 'invalid-color',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['header_background_color']);
    }

    // ========== Multi-Tenant Isolation Tests ==========

    public function test_branding_changes_for_tenant_a_do_not_affect_tenant_b(): void
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        $this->user->tenants()->attach($tenantB->id);

        $tokenA = $this->createTokenForTenant($this->tenant);
        $tokenB = $this->createTokenForTenant($tenantB);

        // Tenant A sets a custom primary color
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/admin/config/branding', [
                'primary_color' => '#FF0000',
            ])
            ->assertStatus(200);

        // Clear cached settings instance
        app()->forgetInstance(BrandingSettings::class);

        // Tenant B should still see the global default (not Tenant A's color)
        $responseB = $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->getJson('/api/config/branding');

        $responseB->assertStatus(200);
        $this->assertNotEquals('#FF0000', $responseB->json('data.primary_color'));
        $this->assertEquals('#0d47a1', $responseB->json('data.primary_color'));
    }

    // ========== Font Schema Tests ==========

    public function test_get_branding_config_returns_font_schema_defaults(): void
    {
        $response = $this->getJson('/api/config/branding');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNull($data['font_family_heading']);
        $this->assertNull($data['font_family_body']);
        $this->assertEquals('default', $data['font_scale']);
        $this->assertEquals([], $data['font_faces']);
    }

    public function test_post_branding_config_updates_font_schema(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'font_family_heading' => 'Montserrat, sans-serif',
                'font_family_body' => 'Open Sans, sans-serif',
                'font_scale' => 'large',
                'font_faces' => [
                    ['family' => 'House Sans', 'src' => 'fonts/custom/house-sans.woff2', 'weight' => 400, 'style' => 'normal'],
                ],
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('Montserrat, sans-serif', $data['font_family_heading']);
        $this->assertEquals('Open Sans, sans-serif', $data['font_family_body']);
        $this->assertEquals('large', $data['font_scale']);
        $this->assertCount(1, $data['font_faces']);
        $this->assertEquals('House Sans', $data['font_faces'][0]['family']);
        $this->assertStringContainsString('house-sans.woff2', $data['font_faces'][0]['src']);

        $settings = app(BrandingSettings::class);
        $this->assertEquals('large', $settings->font_scale);
    }

    public function test_post_branding_config_validates_font_family_characters(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'font_family_heading' => 'Evil<script>alert(1)</script>',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['font_family_heading']);
    }

    public function test_post_branding_config_validates_font_scale(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'font_scale' => 'huge',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['font_scale']);
    }

    public function test_post_branding_config_validates_font_face_src_extension(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'font_faces' => [
                    ['family' => 'House Sans', 'src' => 'fonts/custom/house-sans.ttf', 'weight' => 400],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['font_faces.0.src']);
    }

    public function test_post_branding_config_validates_font_face_weight(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/config/branding', [
                'font_faces' => [
                    ['family' => 'House Sans', 'src' => 'fonts/custom/house-sans.woff2', 'weight' => 50],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['font_faces.0.weight']);
    }

    public function test_get_branding_config_filters_invalid_theme_level_font_values(): void
    {
        // Theme-layer JSON bypasses UpdateBrandingRequest, so simulate raw stored values.
        $settings = app(BrandingSettings::class);
        $settings->font_family_heading = "Font'; } body { color: red } /*";
        $settings->font_faces = [
            ['family' => 'Evil', 'src' => "') } body { color: red } /* x.woff2", 'weight' => 400],
            ['family' => 'Good', 'src' => 'fonts/good.woff2', 'weight' => '400; } * { x: y } /*', 'style' => 'weird'],
        ];
        $settings->save();

        $data = $this->getJson('/api/config/branding')->assertStatus(200)->json('data');

        $this->assertNull($data['font_family_heading']);
        $this->assertCount(1, $data['font_faces']);
        $this->assertSame('Good', $data['font_faces'][0]['family']);
        $this->assertSame(400, $data['font_faces'][0]['weight']);
        $this->assertSame('normal', $data['font_faces'][0]['style']);
        $this->assertStringEndsWith('/storage/fonts/good.woff2', $data['font_faces'][0]['src']);
    }
}
