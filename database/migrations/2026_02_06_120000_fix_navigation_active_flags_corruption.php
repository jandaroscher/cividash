<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fix corruption caused by 2026_02_04_151700_backfill_navigation_active_flags.
     *
     * That migration treated locale keys ('de', 'en') in the translatable JSON structure
     * {"de": [{items}], "en": [{items}]} as navigation items, adding is_active: true at
     * the locale level. This migration removes those spurious keys and ensures is_active
     * is set on actual items within each locale array.
     */
    public function up(): void
    {
        $this->fixColumn('navigations', 'navigation_items');
        $this->fixColumn('footer_navigations', 'footer_navigation_items');
        $this->fixColumn('footer_navigations', 'social_links');
    }

    /**
     * Fix a single JSON column across all rows of a table.
     */
    private function fixColumn(string $table, string $column): void
    {
        $rows = DB::table($table)->get();

        foreach ($rows as $row) {
            $data = json_decode($row->$column, true);
            if (! is_array($data)) {
                continue;
            }

            $changed = false;
            $data = $this->fixStructure($data, $changed);

            if ($changed) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($data)]);
            }
        }
    }

    /**
     * Detect and fix the corrupted translatable structure.
     *
     * Expected structure: {"de": [{item}, ...], "en": [{item}, ...]}
     * Corrupted structure: {"de": [{item}, ..., "is_active": true], "en": [..., "is_active": true]}
     *
     * The corruption adds is_active at the locale-array level. This method:
     * 1. Detects the locale structure (has 'de' or 'en' keys)
     * 2. Removes non-array entries (like is_active: true) from the locale level
     * 3. Removes non-array entries from within each locale's items array
     * 4. Re-indexes arrays to remove gaps
     * 5. Ensures each actual item has is_active set
     */
    private function fixStructure(array $data, bool &$changed): array
    {
        // Check if this is a translatable structure with locale keys
        $isTranslatableStructure = isset($data['de']) || isset($data['en']);

        if ($isTranslatableStructure) {
            // Remove any non-locale keys added at the top level (e.g. is_active: true)
            foreach ($data as $key => $value) {
                if (! in_array($key, ['de', 'en'], true)) {
                    unset($data[$key]);
                    $changed = true;
                }
            }

            // Fix each locale's items array
            foreach (['de', 'en'] as $locale) {
                if (! isset($data[$locale])) {
                    continue;
                }

                if (! is_array($data[$locale])) {
                    $data[$locale] = [];
                    $changed = true;

                    continue;
                }

                $data[$locale] = $this->cleanItems($data[$locale], $changed);
            }
        } else {
            // Direct items array (non-translatable or already locale-resolved)
            $data = $this->cleanItems($data, $changed);
        }

        return $data;
    }

    /**
     * Remove non-array entries from an items array and ensure is_active is set on each item.
     */
    private function cleanItems(array $items, bool &$changed): array
    {
        $originalCount = count($items);
        $items = array_values(array_filter($items, fn ($item) => is_array($item)));

        if (count($items) !== $originalCount) {
            $changed = true;
        }

        foreach ($items as $index => $item) {
            if (! array_key_exists('is_active', $item)) {
                $items[$index]['is_active'] = true;
                $changed = true;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $items[$index]['children'] = $this->cleanItems($item['children'], $changed);
            }
        }

        return $items;
    }

    public function down(): void
    {
        // No rollback needed - this migration only fixes corrupted data
    }
};
