<?php

use App\Models\FooterNavigation;
use App\Models\Navigation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Migrates header and footer settings stored in the settings table into Navigation and FooterNavigation models.
     *
     * The migration is idempotent: it creates records with id 1 only if they do not already exist. It copies and converts
     * translatable fields (navigation_items, footer_navigation_items, social_links, copyright_text) for the 'de' and 'en'
     * locales and applies sensible defaults when settings or individual payload keys are missing.
     */
    public function up(): void
    {
        // Migrate Header Settings to Navigation Model
        $headerSettings = DB::table('settings')
            ->where('group', 'header')
            ->where('name', 'header')
            ->first();

        if ($headerSettings) {
            $payload = json_decode($headerSettings->payload, true);

            // Skip if payload is invalid
            if (! is_array($payload)) {
                $payload = [];
            }

            // Check if Navigation record already exists (idempotent)
            if (! Navigation::find(1)) {
                $navigation = Navigation::create([
                    'id' => 1,
                    'navigation_items' => [],
                    'show_language_switcher' => $payload['show_language_switcher'] ?? true,
                    'dropdown_enabled' => $payload['dropdown_enabled'] ?? false,
                ]);

                // Set translatable navigation_items using setTranslation
                if (isset($payload['navigation_items'])) {
                    $navigation->setTranslation('navigation_items', 'de', $payload['navigation_items']);
                    $navigation->setTranslation('navigation_items', 'en', $payload['navigation_items']);
                } else {
                    $navigation->setTranslation('navigation_items', 'de', []);
                    $navigation->setTranslation('navigation_items', 'en', []);
                }

                $navigation->save();
            }
        } else {
            // Create default Navigation record if no settings exist
            if (! Navigation::find(1)) {
                $navigation = Navigation::create([
                    'id' => 1,
                    'navigation_items' => [],
                    'show_language_switcher' => true,
                    'dropdown_enabled' => false,
                ]);

                // Set empty translatable navigation_items for both locales
                $navigation->setTranslation('navigation_items', 'de', []);
                $navigation->setTranslation('navigation_items', 'en', []);
                $navigation->save();
            }
        }

        // Migrate Footer Settings to FooterNavigation Model
        $footerSettings = DB::table('settings')
            ->where('group', 'footer')
            ->where('name', 'footer')
            ->first();

        if ($footerSettings) {
            $payload = json_decode($footerSettings->payload, true);

            // Skip if payload is invalid
            if (! is_array($payload)) {
                $payload = [];
            }

            // Convert social_links to translatable format if needed
            $socialLinks = $payload['social_links'] ?? [];
            if (! empty($socialLinks) && isset($socialLinks[0])) {
                // Convert non-translatable social_links to translatable format
                $socialLinks = array_map(function ($link) {
                    $convertedLink = $link;
                    // Convert title to translatable format if it's a string
                    if (isset($link['title']) && is_string($link['title'])) {
                        $convertedLink['title'] = ['de' => $link['title'], 'en' => $link['title']];
                    }

                    return $convertedLink;
                }, $socialLinks);
            }

            // Store social_links as translatable (same data for both locales)
            $socialLinksDe = $socialLinks;
            $socialLinksEn = $socialLinks;

            // Check if FooterNavigation record already exists (idempotent)
            if (! FooterNavigation::find(1)) {
                $footer = FooterNavigation::create([
                    'id' => 1,
                    'footer_navigation_items' => [],
                    'social_links' => [],
                    'layout_type' => $payload['layout_type'] ?? 'single-row',
                    'columns' => $payload['columns'] ?? 3,
                    'social_links_enabled' => $payload['social_links_enabled'] ?? true,
                    'copyright_text' => null,
                ]);

                // Set translatable fields using setTranslation
                if (isset($payload['footer_navigation_items'])) {
                    $footer->setTranslation('footer_navigation_items', 'de', $payload['footer_navigation_items']);
                    $footer->setTranslation('footer_navigation_items', 'en', $payload['footer_navigation_items']);
                }

                if (! empty($socialLinksDe)) {
                    $footer->setTranslation('social_links', 'de', $socialLinksDe);
                    $footer->setTranslation('social_links', 'en', $socialLinksEn);
                }

                if (isset($payload['copyright_text'])) {
                    // copyright_text might already be in translatable format
                    if (is_array($payload['copyright_text'])) {
                        $footer->setTranslation('copyright_text', 'de', $payload['copyright_text']['de'] ?? '');
                        $footer->setTranslation('copyright_text', 'en', $payload['copyright_text']['en'] ?? '');
                    } else {
                        $footer->setTranslation('copyright_text', 'de', $payload['copyright_text']);
                        $footer->setTranslation('copyright_text', 'en', $payload['copyright_text']);
                    }
                }

                $footer->save();
            }
        } else {
            // Create default FooterNavigation record if no settings exist
            if (! FooterNavigation::find(1)) {
                $footer = FooterNavigation::create([
                    'id' => 1,
                    'footer_navigation_items' => [],
                    'social_links' => [],
                    'layout_type' => 'single-row',
                    'columns' => 3,
                    'social_links_enabled' => true,
                    'copyright_text' => null,
                ]);

                // Set empty translatable fields for both locales
                $footer->setTranslation('footer_navigation_items', 'de', []);
                $footer->setTranslation('footer_navigation_items', 'en', []);
                $footer->setTranslation('social_links', 'de', []);
                $footer->setTranslation('social_links', 'en', []);
                $footer->save();
            }
        }
    }

    /**
     * Remove the migrated navigation records created by this migration.
     *
     * WARNING: This is a destructive operation that will result in data loss.
     * This deletes the Navigation and FooterNavigation records with id 1. It does not restore any data in the original Settings table; the migration is intended to be one-way and Settings must be restored manually if required.
     *
     * In production environments, this method will throw a RuntimeException to prevent accidental data loss.
     */
    public function down(): void
    {
        // Prevent accidental destructive rollbacks in production
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Rolling back this migration in production will result in data loss. '.
                'Please restore data manually before rolling back.'
            );
        }

        // Delete Navigation and FooterNavigation records
        Navigation::where('id', 1)->delete();
        FooterNavigation::where('id', 1)->delete();
    }
};
