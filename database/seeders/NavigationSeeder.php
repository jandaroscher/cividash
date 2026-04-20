<?php

namespace Database\Seeders;

use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder for Header and Footer Navigation from reference site.
 *
 * Seeds navigation items based on the stadt-regensburg-dashboard reference site.
 * Supports idempotent behavior and bilingual labels.
 */
class NavigationSeeder extends Seeder
{
    /**
     * Seed header and footer navigation items using the given map of page slugs to IDs.
     *
     * @param  array<string,int>  $pageIdMap  Map of page slug (DE) to database ID
     */
    public function run(array $pageIdMap): void
    {
        $this->seedHeaderNavigation($pageIdMap);
        $this->seedFooterNavigation($pageIdMap);
    }

    /**
     * Seed header navigation items.
     *
     * @param  array<string, int>  $pageIdMap  Map of page slug (DE) to database ID
     */
    protected function seedHeaderNavigation(array $pageIdMap): void
    {
        $navigation = Navigation::getOrCreateInstance();

        // Check if navigation already exists (only skip if we have items AND they match expected structure)
        // Empty array means nothing was seeded yet, so we should seed
        $existingItems = $navigation->getTranslation('navigation_items', 'de', false) ?? [];
        if (! empty($existingItems) && count($existingItems) > 0) {
            if ($this->command) {
                $this->command->info('Header navigation already exists, skipping...');
            }

            return;
        }

        $navigationItems = [];

        // Download page
        if (isset($pageIdMap['download'])) {
            $downloadPage = Page::find($pageIdMap['download']);
            if ($downloadPage) {
                $navigationItems[] = [
                    'type' => 'page',
                    'page_id' => $downloadPage->id,
                    'label' => [
                        'de' => $downloadPage->getTranslation('title', 'de', false) ?: 'Download',
                        'en' => $downloadPage->getTranslation('title', 'en', false) ?: 'Download',
                    ],
                    'url' => [
                        'de' => $downloadPage->getUrl(['locale' => 'de']),
                        'en' => $downloadPage->getUrl(['locale' => 'en']),
                    ],
                ];
            }
        }

        // Kontakt/Contact page
        if (isset($pageIdMap['kontakt'])) {
            $contactPage = Page::find($pageIdMap['kontakt']);
            if ($contactPage) {
                $navigationItems[] = [
                    'type' => 'page',
                    'page_id' => $contactPage->id,
                    'label' => [
                        'de' => $contactPage->getTranslation('title', 'de', false) ?: 'Kontakt',
                        'en' => $contactPage->getTranslation('title', 'en', false) ?: 'Contact',
                    ],
                    'url' => [
                        'de' => $contactPage->getUrl(['locale' => 'de']),
                        'en' => $contactPage->getUrl(['locale' => 'en']),
                    ],
                ];
            }
        }

        $navigation->setTranslation('navigation_items', 'de', $navigationItems);
        $navigation->setTranslation('navigation_items', 'en', $navigationItems);
        $navigation->save();

        if ($this->command) {
            $this->command->info('Header navigation seeded successfully');
        }
    }

    /**
     * Seed default footer navigation items when none exist.
     *
     * Writes footer navigation translations for German ('de') and English ('en') and persists the FooterNavigation instance.
     *
     * @param  array<string,int>  $pageIdMap  Map of page slug (DE) to database ID
     */
    protected function seedFooterNavigation(array $pageIdMap): void
    {
        $footer = FooterNavigation::getOrCreateInstance();

        // Check if navigation already exists (only skip if we have items AND they match expected structure)
        // Empty array means nothing was seeded yet, so we should seed
        $existingItems = $footer->getTranslation('footer_navigation_items', 'de', false) ?? [];
        if (! empty($existingItems) && count($existingItems) > 0) {
            if ($this->command) {
                $this->command->info('Footer navigation already exists, skipping...');
            }

            return;
        }

        $navigationItems = [];

        // Impressum page
        if (isset($pageIdMap['impressum'])) {
            $imprintPage = Page::find($pageIdMap['impressum']);
            if ($imprintPage) {
                $navigationItems[] = [
                    'type' => 'page',
                    'page_id' => $imprintPage->id,
                    'label' => [
                        'de' => $imprintPage->getTranslation('title', 'de', false) ?: 'Impressum',
                        'en' => $imprintPage->getTranslation('title', 'en', false) ?: 'Imprint',
                    ],
                    'url' => [
                        'de' => $imprintPage->getUrl(['locale' => 'de']),
                        'en' => $imprintPage->getUrl(['locale' => 'en']),
                    ],
                ];
            }
        }

        // Datenschutz page
        if (isset($pageIdMap['datenschutz'])) {
            $privacyPage = Page::find($pageIdMap['datenschutz']);
            if ($privacyPage) {
                $navigationItems[] = [
                    'type' => 'page',
                    'page_id' => $privacyPage->id,
                    'label' => [
                        'de' => $privacyPage->getTranslation('title', 'de', false) ?: 'Datenschutz',
                        'en' => $privacyPage->getTranslation('title', 'en', false) ?: 'Privacy',
                    ],
                    'url' => [
                        'de' => $privacyPage->getUrl(['locale' => 'de']),
                        'en' => $privacyPage->getUrl(['locale' => 'en']),
                    ],
                ];
            }
        }

        // External links (manual type) - same URL for both locales
        $navigationItems[] = [
            'type' => 'manual',
            'page_id' => null,
            'label' => [
                'de' => 'regensburg.de',
                'en' => 'regensburg.de',
            ],
            'url' => [
                'de' => 'https://www.regensburg.de/',
                'en' => 'https://www.regensburg.de/',
            ],
        ];

        $navigationItems[] = [
            'type' => 'manual',
            'page_id' => null,
            'label' => [
                'de' => 'mein.regensburg.de',
                'en' => 'mein.regensburg.de',
            ],
            'url' => [
                'de' => 'https://mein.regensburg.de/',
                'en' => 'https://mein.regensburg.de/',
            ],
        ];

        $footer->setTranslation('footer_navigation_items', 'de', $navigationItems);
        $footer->setTranslation('footer_navigation_items', 'en', $navigationItems);

        // Copy sponsor logos to public storage
        $assetsDir = database_path('seeders/assets/footer-sponsors');
        if (is_dir($assetsDir)) {
            Storage::disk('public')->makeDirectory('footer-sponsors');
            foreach (glob($assetsDir.'/*.svg') as $file) {
                Storage::disk('public')->put(
                    'footer-sponsors/'.basename($file),
                    file_get_contents($file)
                );
            }
        }

        // Placeholder sponsors without links; real logos are configured per tenant in the CMS.
        $sponsors = [
            ['image' => 'footer-sponsors/logo-sponsor-1.svg', 'url' => '', 'name' => 'Sponsor 1'],
            ['image' => 'footer-sponsors/logo-sponsor-2.svg', 'url' => '', 'name' => 'Sponsor 2'],
        ];
        $footer->setTranslation('sponsors', 'de', $sponsors);
        $footer->setTranslation('sponsors', 'en', $sponsors);

        // Copy social icons to public storage
        $socialAssetsDir = database_path('seeders/assets/footer-social-icons');
        if (is_dir($socialAssetsDir)) {
            Storage::disk('public')->makeDirectory('footer-social-icons');
            foreach (glob($socialAssetsDir.'/*.svg') as $file) {
                Storage::disk('public')->put(
                    'footer-social-icons/'.basename($file),
                    file_get_contents($file)
                );
            }
        }

        // Social Links (fields: icon, link, title — matching Filament form). Inactive
        // placeholders until a tenant enters its own profile URLs.
        $socialLinks = [
            ['icon' => 'footer-social-icons/facebook.svg', 'link' => 'https://example.org', 'title' => 'Facebook', 'is_active' => false],
            ['icon' => 'footer-social-icons/twitter.svg', 'link' => 'https://example.org', 'title' => 'Twitter', 'is_active' => false],
            ['icon' => 'footer-social-icons/instagram.svg', 'link' => 'https://example.org', 'title' => 'Instagram', 'is_active' => false],
            ['icon' => 'footer-social-icons/youtube.svg', 'link' => 'https://example.org', 'title' => 'Youtube', 'is_active' => false],
        ];
        $footer->setTranslation('social_links', 'de', $socialLinks);
        $footer->setTranslation('social_links', 'en', $socialLinks);
        $footer->social_links_enabled = true;

        $footer->save();

        if ($this->command) {
            $this->command->info('Footer navigation seeded successfully');
        }
    }
}
