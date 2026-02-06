<?php

namespace Tests\Feature;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Page;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderFooterApiTranslatableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and authenticate for Filament tenant context
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();
        if ($tenant) {
            $user->tenants()->sync([$tenant->id]);
            Filament::auth()->login($user);
            Filament::setTenant($tenant);
        }
    }

    /**
     * Test that header API endpoint returns translated navigation items for German locale.
     */
    public function test_header_api_returns_german_translations(): void
    {
        $navigation = Navigation::getOrCreateInstance();
        $navigation->setTranslation('navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
        ]);
        $navigation->setTranslation('navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
        ]);
        $navigation->save();

        $response = $this->getJson('/api/config/header?locale=de');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'navigation_items' => [
                    [
                        'type' => 'manual',
                        'label' => 'Kontakt',
                        'url' => '/kontakt',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test that header API endpoint returns translated navigation items for English locale.
     */
    public function test_header_api_returns_english_translations(): void
    {
        $navigation = Navigation::getOrCreateInstance();
        $navigation->setTranslation('navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
        ]);
        $navigation->setTranslation('navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
        ]);
        $navigation->save();

        $response = $this->getJson('/api/config/header?locale=en');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'navigation_items' => [
                    [
                        'type' => 'manual',
                        'label' => 'Contact',
                        'url' => '/en/contact',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test that header API endpoint falls back to app locale if no locale parameter provided.
     */
    public function test_header_api_falls_back_to_app_locale(): void
    {
        app()->setLocale('en');

        $navigation = Navigation::getOrCreateInstance();
        $navigation->setTranslation('navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
        ]);
        $navigation->setTranslation('navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
        ]);
        $navigation->save();

        $response = $this->getJson('/api/config/header');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'navigation_items' => [
                    [
                        'label' => 'Contact',
                        'url' => '/en/contact',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test that footer API endpoint returns translated navigation items and copyright text.
     */
    public function test_footer_api_returns_translated_content(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();
        $footer->setTranslation('footer_navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'url' => ['de' => '/impressum', 'en' => '/en/imprint'],
            ],
        ]);
        $footer->setTranslation('footer_navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'url' => ['de' => '/impressum', 'en' => '/en/imprint'],
            ],
        ]);
        $footer->setTranslation('copyright_text', 'de', '© 2025 Beispiel');
        $footer->setTranslation('copyright_text', 'en', '© 2025 Example');
        $footer->save();

        $response = $this->getJson('/api/config/footer?locale=en');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'footer_navigation_items' => [
                    [
                        'type' => 'manual',
                        'label' => 'Imprint',
                        'url' => '/en/imprint',
                    ],
                ],
                'copyright_text' => '© 2025 Example',
                'social_links' => [],
            ],
        ]);
    }

    /**
     * Test that API endpoints validate locale parameter.
     */
    public function test_api_endpoints_validate_locale_parameter(): void
    {
        $response = $this->getJson('/api/config/header?locale=invalid');

        // Should fall back to app locale, not error
        $response->assertStatus(200);

        $response = $this->getJson('/api/config/footer?locale=fr');

        // Should fall back to app locale, not error
        $response->assertStatus(200);
    }

    /**
     * Test that header API handles nested children in navigation items.
     */
    public function test_header_api_handles_nested_children(): void
    {
        $navigation = Navigation::getOrCreateInstance();
        $navigation->setTranslation('navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Hauptmenü', 'en' => 'Main Menu'],
                'url' => ['de' => '/haupt', 'en' => '/en/main'],
                'children' => [
                    [
                        'type' => 'manual',
                        'label' => ['de' => 'Untermenü', 'en' => 'Submenu'],
                        'url' => ['de' => '/unter', 'en' => '/en/sub'],
                    ],
                ],
            ],
        ]);
        $navigation->setTranslation('navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Hauptmenü', 'en' => 'Main Menu'],
                'url' => ['de' => '/haupt', 'en' => '/en/main'],
                'children' => [
                    [
                        'type' => 'manual',
                        'label' => ['de' => 'Untermenü', 'en' => 'Submenu'],
                        'url' => ['de' => '/unter', 'en' => '/en/sub'],
                    ],
                ],
            ],
        ]);
        $navigation->save();

        $response = $this->getJson('/api/config/header?locale=en');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Main Menu', $data['data']['navigation_items'][0]['label']);
        $this->assertEquals('Submenu', $data['data']['navigation_items'][0]['children'][0]['label']);
    }

    public function test_header_api_filters_inactive_page_items(): void
    {
        $inactivePage = Page::create([
            'title' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
            'slug' => ['de' => 'inaktiv', 'en' => 'inactive'],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
            'is_public' => false,
        ]);

        $navigation = Navigation::getOrCreateInstance();
        $navigation->setTranslation('navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
            [
                'type' => 'page',
                'page_id' => $inactivePage->id,
                'label' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
                'url' => ['de' => '/inaktiv', 'en' => '/en/inactive'],
            ],
        ]);
        $navigation->setTranslation('navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/en/contact'],
            ],
            [
                'type' => 'page',
                'page_id' => $inactivePage->id,
                'label' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
                'url' => ['de' => '/inaktiv', 'en' => '/en/inactive'],
            ],
        ]);
        $navigation->save();

        $response = $this->getJson('/api/config/header?locale=de');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'navigation_items' => [
                    [
                        'type' => 'manual',
                        'label' => 'Kontakt',
                        'url' => '/kontakt',
                    ],
                ],
            ],
        ]);
    }

    public function test_footer_api_filters_inactive_page_items(): void
    {
        $inactivePage = Page::create([
            'title' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
            'slug' => ['de' => 'inaktiv', 'en' => 'inactive'],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
            'is_public' => false,
        ]);

        $footer = FooterNavigation::getOrCreateInstance();
        $footer->setTranslation('footer_navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'url' => ['de' => '/impressum', 'en' => '/en/imprint'],
            ],
            [
                'type' => 'page',
                'page_id' => $inactivePage->id,
                'label' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
                'url' => ['de' => '/inaktiv', 'en' => '/en/inactive'],
            ],
        ]);
        $footer->setTranslation('footer_navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'url' => ['de' => '/impressum', 'en' => '/en/imprint'],
            ],
            [
                'type' => 'page',
                'page_id' => $inactivePage->id,
                'label' => ['de' => 'Inaktiv', 'en' => 'Inactive'],
                'url' => ['de' => '/inaktiv', 'en' => '/en/inactive'],
            ],
        ]);
        $footer->save();

        $response = $this->getJson('/api/config/footer?locale=de');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'footer_navigation_items' => [
                    [
                        'type' => 'manual',
                        'label' => 'Impressum',
                        'url' => '/impressum',
                    ],
                ],
            ],
        ]);
    }
}
