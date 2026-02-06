<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfills navigation structures to ensure navigation items include required fields.
     *
     * Ensures navigation and footer navigation item JSON columns are normalized (adds missing `is_active` flags and normalizes nested `children`) so existing records are updated with the expected shape.
     */
    public function up(): void
    {
        // Backfill navigations.navigation_items
        $this->backfillNavigationItems();

        // Backfill footer_navigations.footer_navigation_items and social_links
        $this->backfillFooterItems();
    }

    /**
     * Backfills navigation records by ensuring each navigation's items include required defaults and persisting changes.
     *
     * Decodes the `navigation_items` JSON for every row in the `navigations` table, skips rows where decoding does not produce an array, normalizes items (via normalizeItems) to add missing fields such as `is_active`, and updates the `navigation_items` column with the new JSON only when changes were made.
     */
    private function backfillNavigationItems(): void
    {
        $navigations = DB::table('navigations')->get();

        foreach ($navigations as $navigation) {
            $items = json_decode($navigation->navigation_items, true);
            if (! is_array($items)) {
                continue;
            }

            $changed = false;
            $items = $this->normalizeItems($items, $changed);

            if ($changed) {
                DB::table('navigations')
                    ->where('id', $navigation->id)
                    ->update(['navigation_items' => json_encode($items)]);
            }
        }
    }

    /**
     * Ensure footer navigation and social link items include default `is_active` flags and persist any changes.
     *
     * Decodes `footer_navigation_items` and `social_links` from the `footer_navigations` table, normalizes each item array
     * (adding missing `is_active` fields and normalizing nested children), and updates the database rows when modifications
     * are detected.
     */
    private function backfillFooterItems(): void
    {
        $footers = DB::table('footer_navigations')->get();

        foreach ($footers as $footer) {
            $updates = [];

            // Footer navigation items
            $footerItems = json_decode($footer->footer_navigation_items, true);
            if (is_array($footerItems)) {
                $changed = false;
                $footerItems = $this->normalizeItems($footerItems, $changed);
                if ($changed) {
                    $updates['footer_navigation_items'] = json_encode($footerItems);
                }
            }

            // Social links
            $socialLinks = json_decode($footer->social_links, true);
            if (is_array($socialLinks)) {
                $changed = false;
                $socialLinks = $this->normalizeItems($socialLinks, $changed);
                if ($changed) {
                    $updates['social_links'] = json_encode($socialLinks);
                }
            }

            if (! empty($updates)) {
                DB::table('footer_navigations')
                    ->where('id', $footer->id)
                    ->update($updates);
            }
        }
    }

    /**
     * Ensure each navigation item and its nested children include an `is_active` flag, adding `is_active => true` when missing.
     *
     * @param  array  $items  The list of navigation items to normalize; each item may contain a `children` array of sub-items.
     * @param  bool  &$changed  Set to `true` if any item (or nested child) was modified by adding the `is_active` flag.
     * @return array The normalized list of navigation items with `is_active` present on every item.
     */
    private function normalizeItems(array $items, bool &$changed): array
    {
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! array_key_exists('is_active', $item)) {
                $item['is_active'] = true;
                $changed = true;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->normalizeItems($item['children'], $changed);
            }

            $items[$index] = $item;
        }

        return $items;
    }

    /**
     * Intentionally empty rollback; leaves backfilled `is_active` flags unchanged.
     *
     * This migration does not reverse the backfill performed in up(), so no actions
     * are taken when rolling back.
     */
    public function down(): void
    {
        // No rollback needed - is_active fields can remain
    }
};
