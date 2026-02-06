<?php

use Illuminate\Support\Facades\Log;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Migrates footer navigation data from `footer.footer_links` to `footer.footer_navigation_items`.
     *
     * This migration automatically transforms existing footer_links data into the new
     * footer_navigation_items structure. Each link is converted to a navigation item with:
     * - type: 'manual' (default, as old links were manual entries)
     * - url: extracted from 'url', 'href', or 'link' fields
     * - label: extracted from 'label', 'title', 'text', or 'name' fields
     * - target, order: preserved if present in original data
     *
     * Data format expectations:
     * - footer_links payload can be a JSON string or already decoded array
     * - Links without both URL and label are skipped during migration
     * - The migration is idempotent: if footer_navigation_items already exists, it skips migration
     *
     * Note: The original footer_links setting is preserved for backwards compatibility
     * and can be removed in a future migration if needed.
     */
    public function up(): void
    {
        try {
            // Check if footer_navigation_items already exists (idempotent check)
            $existingNavigationItems = DB::table('settings')
                ->where('group', 'footer')
                ->where('name', 'footer_navigation_items')
                ->first();

            if ($existingNavigationItems) {
                // Already migrated, skip
                return;
            }

            // Fetch existing footer_links value
            $footerLinksSetting = DB::table('settings')
                ->where('group', 'footer')
                ->where('name', 'footer_links')
                ->first();

            $migratedItems = [];

            if ($footerLinksSetting) {
                try {
                    // Parse the payload (could be JSON string or already decoded)
                    $payload = $footerLinksSetting->payload;

                    if (is_string($payload)) {
                        $decoded = json_decode($payload, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $footerLinks = $decoded;
                        } else {
                            // If JSON decode fails, try to extract array from string
                            $footerLinks = [];
                            Log::warning('Failed to decode footer_links JSON in migration', [
                                'error' => json_last_error_msg(),
                                'payload' => $payload,
                            ]);
                        }
                    } else {
                        $footerLinks = $payload;
                    }

                    // Transform each link to new navigation item structure
                    if (is_array($footerLinks)) {
                        foreach ($footerLinks as $index => $link) {
                            // Handle different possible structures
                            $item = [
                                'type' => 'manual', // Default to manual since old links were manual
                            ];

                            // Map common fields
                            if (isset($link['url']) || isset($link['href']) || isset($link['link'])) {
                                $item['url'] = $link['url'] ?? $link['href'] ?? $link['link'] ?? '#';
                            } else {
                                $item['url'] = '#';
                            }

                            if (isset($link['label']) || isset($link['title']) || isset($link['text']) || isset($link['name'])) {
                                $item['label'] = $link['label'] ?? $link['title'] ?? $link['text'] ?? $link['name'] ?? '';
                            } else {
                                $item['label'] = '';
                            }

                            // Preserve any additional metadata
                            if (isset($link['target'])) {
                                $item['target'] = $link['target'];
                            }
                            if (isset($link['order'])) {
                                $item['order'] = $link['order'];
                            } elseif (isset($index)) {
                                $item['order'] = $index;
                            }

                            // Only add item if it has a meaningful URL or label
                            $hasMeaningfulUrl = ! empty($item['url']) && $item['url'] !== '#';
                            if ($hasMeaningfulUrl || ! empty($item['label'])) {
                                $migratedItems[] = $item;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error parsing footer_links during migration', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue with empty array if parsing fails
                    $migratedItems = [];
                }
            }

            // Write the migrated items to footer_navigation_items
            $this->migrator->add('footer.footer_navigation_items', $migratedItems);

            // Note: We keep footer_links for backwards compatibility
            // It can be removed in a future migration if needed
        } catch (\Exception $e) {
            Log::error('Error during footer_links migration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re-throw to fail the migration
            throw $e;
        }
    }

    /**
     * Reverses the up() migration by removing the `footer.footer_navigation_items` setting.
     *
     * Note: This does not restore the original footer_links data, as it may have been
     * modified after migration. Manual restoration may be required if needed.
     */
    public function down(): void
    {
        try {
            // Remove the footer_navigation_items setting
            $this->migrator->delete('footer.footer_navigation_items');
        } catch (\Exception $e) {
            Log::error('Error during footer_links migration rollback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re-throw to fail the rollback
            throw $e;
        }
    }
};
