<?php

namespace Tests\Feature\Accessibility;

use App\Models\Page;
use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemedFabricatorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        
        // Create a test page without blocks to avoid hero block image variable issues
        Page::create([
            'title' => ['de' => 'Test Page', 'en' => ''],
            'slug' => ['de' => 'test-page', 'en' => ''],
            'layout' => 'landingpage',
            'blocks' => ['de' => [], 'en' => []],
        ]);
    }

    public function test_fabricator_page_renders_with_theme_css_variables(): void
    {
        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('--primary-color', false);
        $response->assertSee('--header-background-color', false);
        $response->assertSee('--footer-background-color', false);
        $response->assertSee('--font-family', false);
    }

    public function test_fabricator_page_uses_header_background_color(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->header_background_color = '#FF0000';
        $settings->save();

        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('--header-background-color: #FF0000', false);
    }

    public function test_fabricator_page_uses_footer_background_color(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->footer_background_color = '#00FF00';
        $settings->save();

        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('--footer-background-color: #00FF00', false);
    }

    public function test_fabricator_page_loads_google_fonts_when_configured(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->typography_font_family = 'Roboto';
        $settings->typography_font_weights = [400, 700];
        $settings->save();

        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('fonts.googleapis.com', false);
        $response->assertSee('family=Roboto', false);
    }

    public function test_fabricator_page_loads_custom_font_when_configured(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->typography_custom_font_name = 'Custom Font';
        $settings->typography_custom_font_file = 'fonts/custom/test-font.woff2';
        $settings->save();

        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('@font-face', false);
        $response->assertSee('Custom Font', false);
    }

    public function test_fabricator_page_has_accessible_text_colors(): void
    {
        // Test with light background and dark text
        $settings = app(BrandingSettings::class);
        $settings->header_background_color = '#FFFFFF';
        $settings->text_primary_color = '#000000';
        $settings->save();

        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('--header-background-color: #FFFFFF', false);
        $response->assertSee('--text-primary-color: #000000', false);
    }

    public function test_fabricator_page_has_accessible_link_colors(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->link_color = '#0066CC';
        $settings->link_hover_color = '#004499';
        $settings->save();

        $response = $this->get('/test-page');

        $response->assertStatus(200);
        $response->assertSee('--link-color', false);
        $response->assertSee('--link-hover-color', false);
    }

    public function test_fabricator_page_applies_theme_to_both_layouts(): void
    {
        // Test landingpage layout
        $landingPage = Page::create([
            'title' => ['de' => 'Landing Page', 'en' => ''],
            'slug' => ['de' => 'landing', 'en' => ''],
            'layout' => 'landingpage',
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/landing');
        $response->assertStatus(200);
        $response->assertSee('--header-background-color', false);

        // Test subpage layout
        $subPage = Page::create([
            'title' => ['de' => 'Sub Page', 'en' => ''],
            'slug' => ['de' => 'sub', 'en' => ''],
            'layout' => 'subpage',
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $response = $this->get('/sub');
        $response->assertStatus(200);
        $response->assertSee('--header-background-color', false);
    }
}

