<?php

namespace Tests\Unit;

use App\Models\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_singleton_get_instance_creates_record_if_not_exists(): void
    {
        $navigation = Navigation::getOrCreateInstance();

        $this->assertNotNull($navigation);
        $this->assertEquals(1, $navigation->id);
        $this->assertTrue($navigation->show_language_switcher);
        $this->assertFalse($navigation->dropdown_enabled);
    }

    public function test_singleton_get_instance_returns_existing_record(): void
    {
        // Get or create instance first
        $navigation = Navigation::getOrCreateInstance();
        
        // Update the record
        $navigation->show_language_switcher = false;
        $navigation->dropdown_enabled = true;
        $navigation->save();

        // Now get it via getInstance to verify it returns the updated record
        $retrieved = Navigation::getInstance();

        $this->assertNotNull($retrieved);
        $this->assertEquals(1, $retrieved->id);
        $this->assertFalse($retrieved->show_language_switcher, 'show_language_switcher should be false');
        $this->assertTrue($retrieved->dropdown_enabled, 'dropdown_enabled should be true');
    }

    public function test_translatable_navigation_items(): void
    {
        $navigation = Navigation::getOrCreateInstance();

        $items = [
            [
                'type' => 'manual',
                'label' => ['de' => 'Test DE', 'en' => 'Test EN'],
                'url' => ['de' => '/test-de', 'en' => '/test-en'],
            ],
        ];

        $navigation->setTranslation('navigation_items', 'de', $items);
        $navigation->setTranslation('navigation_items', 'en', $items);
        $navigation->save();

        $deItems = $navigation->getTranslation('navigation_items', 'de', false);
        $enItems = $navigation->getTranslation('navigation_items', 'en', false);

        $this->assertIsArray($deItems);
        $this->assertIsArray($enItems);
        $this->assertEquals('Test DE', $deItems[0]['label']['de']);
        $this->assertEquals('Test EN', $enItems[0]['label']['en']);
    }

    public function test_get_translated_navigation_items(): void
    {
        $navigation = Navigation::getOrCreateInstance();

        $items = [
            [
                'type' => 'manual',
                'label' => ['de' => 'Kontakt', 'en' => 'Contact'],
                'url' => ['de' => '/kontakt', 'en' => '/contact'],
            ],
        ];

        $navigation->setTranslation('navigation_items', 'de', $items);
        $navigation->setTranslation('navigation_items', 'en', $items);
        $navigation->save();

        app()->setLocale('de');
        $deItems = $navigation->getTranslatedNavigationItems('de');
        $this->assertEquals('Kontakt', $deItems[0]['label']);
        $this->assertEquals('/kontakt', $deItems[0]['url']);

        app()->setLocale('en');
        $enItems = $navigation->getTranslatedNavigationItems('en');
        $this->assertEquals('Contact', $enItems[0]['label']);
        $this->assertEquals('/contact', $enItems[0]['url']);
    }
}
