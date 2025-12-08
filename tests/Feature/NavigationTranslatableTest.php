<?php

namespace Tests\Feature;

use App\Models\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTranslatableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that navigation can store and retrieve translatable navigation items.
     */
    public function test_navigation_store_translatable_navigation_items(): void
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
        
        // Reload navigation
        $navigation = Navigation::getInstance();
        
        $items = $navigation->getTranslation('navigation_items', 'de', false);
        $this->assertIsArray($items);
        $this->assertIsArray($items[0]['label']);
        $this->assertEquals('Kontakt', $items[0]['label']['de']);
        $this->assertEquals('Contact', $items[0]['label']['en']);
    }

    /**
     * Test that getTranslatedNavigationItems returns correct translation for locale.
     */
    public function test_get_translated_navigation_items_returns_correct_locale(): void
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
        
        // Reload navigation
        $navigation = Navigation::getInstance();
        
        // Test German locale
        app()->setLocale('de');
        $translated = $navigation->getTranslatedNavigationItems('de');
        $this->assertEquals('Kontakt', $translated[0]['label']);
        $this->assertEquals('/kontakt', $translated[0]['url']);
        
        // Test English locale
        app()->setLocale('en');
        $translated = $navigation->getTranslatedNavigationItems('en');
        $this->assertEquals('Contact', $translated[0]['label']);
        $this->assertEquals('/en/contact', $translated[0]['url']);
    }

    /**
     * Test that getTranslatedNavigationItems handles nested children.
     */
    public function test_get_translated_navigation_items_handles_children(): void
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
        
        // Reload navigation
        $navigation = Navigation::getInstance();
        
        $translated = $navigation->getTranslatedNavigationItems('en');
        $this->assertEquals('Main Menu', $translated[0]['label']);
        $this->assertEquals('Submenu', $translated[0]['children'][0]['label']);
    }

    /**
     * Test that getTranslatedNavigationItems falls back to 'de' if translation is missing.
     */
    public function test_get_translated_navigation_items_falls_back_to_de(): void
    {
        $navigation = Navigation::getOrCreateInstance();
        
        $navigation->setTranslation('navigation_items', 'de', [
            [
                'type' => 'manual',
                'label' => ['de' => 'Nur Deutsch'],
                'url' => ['de' => '/deutsch'],
            ],
        ]);
        
        $navigation->save();
        
        // Reload navigation
        $navigation = Navigation::getInstance();
        
        $translated = $navigation->getTranslatedNavigationItems('en');
        $this->assertEquals('Nur Deutsch', $translated[0]['label']);
        $this->assertEquals('/deutsch', $translated[0]['url']);
    }
}
