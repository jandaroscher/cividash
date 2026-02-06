<?php

namespace Tests\Feature;

use App\Models\FooterNavigation;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterSettingsTranslatableTest extends TestCase
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
     * Test that footer settings can store and retrieve translatable navigation items.
     */
    public function test_footer_settings_store_translatable_navigation_items(): void
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

        $footer->save();

        // Reload footer
        $footer = FooterNavigation::getInstance();

        $items = $footer->getTranslation('footer_navigation_items', 'de', false);
        $this->assertIsArray($items);
        $this->assertIsArray($items[0]['label']);
        $this->assertEquals('Impressum', $items[0]['label']['de']);
        $this->assertEquals('Imprint', $items[0]['label']['en']);
    }

    /**
     * Test that footer settings can store and retrieve translatable copyright text.
     */
    public function test_footer_settings_store_translatable_copyright_text(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $footer->setTranslation('copyright_text', 'de', '© {year} {site_name}');
        $footer->setTranslation('copyright_text', 'en', '© {year} {site_name}');

        $footer->save();

        // Reload footer
        $footer = FooterNavigation::getInstance();

        $copyrightDe = $footer->getTranslation('copyright_text', 'de', false);
        $copyrightEn = $footer->getTranslation('copyright_text', 'en', false);
        $this->assertEquals('© {year} {site_name}', $copyrightDe);
        $this->assertEquals('© {year} {site_name}', $copyrightEn);
    }

    /**
     * Test that getTranslatedFooterNavigationItems returns correct translation for locale.
     */
    public function test_get_translated_footer_navigation_items_returns_correct_locale(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $footer->setTranslation('footer_navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Datenschutz', 'en' => 'Privacy'],
                'url' => ['de' => '/datenschutz', 'en' => '/en/privacy'],
            ],
        ]);
        $footer->setTranslation('footer_navigation_items', 'en', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Datenschutz', 'en' => 'Privacy'],
                'url' => ['de' => '/datenschutz', 'en' => '/en/privacy'],
            ],
        ]);

        $footer->save();

        // Reload footer
        $footer = FooterNavigation::getInstance();

        // Test German locale
        $translated = $footer->getTranslatedFooterNavigationItems('de');
        $this->assertEquals('Datenschutz', $translated[0]['label']);
        $this->assertEquals('/datenschutz', $translated[0]['url']);

        // Test English locale
        $translated = $footer->getTranslatedFooterNavigationItems('en');
        $this->assertEquals('Privacy', $translated[0]['label']);
        $this->assertEquals('/en/privacy', $translated[0]['url']);
    }

    /**
     * Test that getTranslatedCopyrightText returns correct translation for locale.
     */
    public function test_get_translated_copyright_text_returns_correct_locale(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $footer->setTranslation('copyright_text', 'de', '© 2025 Beispiel');
        $footer->setTranslation('copyright_text', 'en', '© 2025 Example');

        $footer->save();

        // Reload footer
        $footer = FooterNavigation::getInstance();

        $this->assertEquals('© 2025 Beispiel', $footer->getTranslatedCopyrightText('de'));
        $this->assertEquals('© 2025 Example', $footer->getTranslatedCopyrightText('en'));
    }

    /**
     * Test that getTranslatedCopyrightText falls back to 'de' if translation is missing.
     */
    public function test_get_translated_copyright_text_falls_back_to_de(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $footer->setTranslation('copyright_text', 'de', '© 2025 Nur Deutsch');

        $footer->save();

        // Reload footer
        $footer = FooterNavigation::getInstance();

        $this->assertEquals('© 2025 Nur Deutsch', $footer->getTranslatedCopyrightText('en'));
    }
}
