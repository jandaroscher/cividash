<?php

namespace Tests\Unit;

use App\Models\FooterNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_singleton_get_instance_creates_record_if_not_exists(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $this->assertNotNull($footer);
        $this->assertEquals(1, $footer->id);
        $this->assertEquals('single-row', $footer->layout_type);
        $this->assertEquals(3, $footer->columns);
        $this->assertTrue($footer->social_links_enabled);
    }

    public function test_singleton_get_instance_returns_existing_record(): void
    {
        // Get or create instance first
        $footer = FooterNavigation::getOrCreateInstance();
        
        // Update the record
        $footer->layout_type = 'grid';
        $footer->columns = 4;
        $footer->social_links_enabled = false;
        $footer->save();

        // Now get it via getInstance to verify it returns the updated record
        $retrieved = FooterNavigation::getInstance();

        $this->assertNotNull($retrieved);
        $this->assertEquals(1, $retrieved->id);
        $this->assertEquals('grid', $retrieved->layout_type, 'layout_type should be grid');
        $this->assertEquals(4, $retrieved->columns, 'columns should be 4');
        $this->assertFalse($retrieved->social_links_enabled, 'social_links_enabled should be false');
    }

    public function test_translatable_footer_navigation_items(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $items = [
            [
                'type' => 'manual',
                'label' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'url' => ['de' => '/impressum', 'en' => '/imprint'],
            ],
        ];

        $footer->setTranslation('footer_navigation_items', 'de', $items);
        $footer->setTranslation('footer_navigation_items', 'en', $items);
        $footer->save();

        $deItems = $footer->getTranslation('footer_navigation_items', 'de', false);
        $enItems = $footer->getTranslation('footer_navigation_items', 'en', false);

        $this->assertIsArray($deItems);
        $this->assertIsArray($enItems);
        $this->assertEquals('Impressum', $deItems[0]['label']['de']);
        $this->assertEquals('Imprint', $enItems[0]['label']['en']);
    }

    public function test_translatable_social_links(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $links = [
            [
                'icon' => 'test-icon.png',
                'link' => 'https://example.com',
                'title' => ['de' => 'Beispiel', 'en' => 'Example'],
            ],
        ];

        $footer->setTranslation('social_links', 'de', $links);
        $footer->setTranslation('social_links', 'en', $links);
        $footer->save();

        $deLinks = $footer->getTranslation('social_links', 'de', false);
        $enLinks = $footer->getTranslation('social_links', 'en', false);

        $this->assertIsArray($deLinks);
        $this->assertIsArray($enLinks);
        $this->assertEquals('Beispiel', $deLinks[0]['title']['de']);
        $this->assertEquals('Example', $enLinks[0]['title']['en']);
    }

    public function test_translatable_copyright_text(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $footer->setTranslation('copyright_text', 'de', '© 2025 Beispiel');
        $footer->setTranslation('copyright_text', 'en', '© 2025 Example');
        $footer->save();

        $deCopyright = $footer->getTranslation('copyright_text', 'de', false);
        $enCopyright = $footer->getTranslation('copyright_text', 'en', false);

        $this->assertEquals('© 2025 Beispiel', $deCopyright);
        $this->assertEquals('© 2025 Example', $enCopyright);
    }

    public function test_get_translated_footer_navigation_items(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $items = [
            [
                'type' => 'manual',
                'label' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'url' => ['de' => '/impressum', 'en' => '/imprint'],
            ],
        ];

        $footer->setTranslation('footer_navigation_items', 'de', $items);
        $footer->setTranslation('footer_navigation_items', 'en', $items);
        $footer->save();

        $deItems = $footer->getTranslatedFooterNavigationItems('de');
        $this->assertEquals('Impressum', $deItems[0]['label']);
        $this->assertEquals('/impressum', $deItems[0]['url']);

        $enItems = $footer->getTranslatedFooterNavigationItems('en');
        $this->assertEquals('Imprint', $enItems[0]['label']);
        $this->assertEquals('/imprint', $enItems[0]['url']);
    }

    public function test_get_translated_social_links(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $links = [
            [
                'icon' => 'test-icon.png',
                'link' => 'https://example.com',
                'title' => ['de' => 'Beispiel', 'en' => 'Example'],
            ],
        ];

        $footer->setTranslation('social_links', 'de', $links);
        $footer->setTranslation('social_links', 'en', $links);
        $footer->save();

        $deLinks = $footer->getTranslatedSocialLinks('de');
        $this->assertEquals('Beispiel', $deLinks[0]['title']);

        $enLinks = $footer->getTranslatedSocialLinks('en');
        $this->assertEquals('Example', $enLinks[0]['title']);
    }

    public function test_get_translated_copyright_text(): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        $footer->setTranslation('copyright_text', 'de', '© 2025 Beispiel');
        $footer->setTranslation('copyright_text', 'en', '© 2025 Example');
        $footer->save();

        $deCopyright = $footer->getTranslatedCopyrightText('de');
        $this->assertEquals('© 2025 Beispiel', $deCopyright);

        $enCopyright = $footer->getTranslatedCopyrightText('en');
        $this->assertEquals('© 2025 Example', $enCopyright);
    }
}
