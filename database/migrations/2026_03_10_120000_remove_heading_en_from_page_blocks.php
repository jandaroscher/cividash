<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate heading_en values into the EN locale blocks and remove the _en fields.
     *
     * Before: Each block in both locales may have heading_en alongside heading.
     * After: EN blocks use heading_en as heading (if heading was empty), _en fields removed.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $pages = DB::table('pages')->whereNotNull('blocks')->get();

        foreach ($pages as $page) {
            $blocksRaw = is_string($page->blocks) ? json_decode($page->blocks, true) : $page->blocks;

            if (! is_array($blocksRaw)) {
                continue;
            }

            $changed = false;

            // Process EN locale: migrate heading_en → heading if heading is empty
            if (isset($blocksRaw['en']) && is_array($blocksRaw['en'])) {
                foreach ($blocksRaw['en'] as &$block) {
                    if (! isset($block['data']) || ! is_array($block['data'])) {
                        continue;
                    }

                    if (array_key_exists('heading_en', $block['data'])) {
                        if (empty($block['data']['heading']) && ! empty($block['data']['heading_en'])) {
                            $block['data']['heading'] = $block['data']['heading_en'];
                        }
                        unset($block['data']['heading_en']);
                        $changed = true;
                    }
                }
                unset($block);
            }

            // Process DE locale: just remove heading_en
            if (isset($blocksRaw['de']) && is_array($blocksRaw['de'])) {
                foreach ($blocksRaw['de'] as &$block) {
                    if (! isset($block['data']) || ! is_array($block['data'])) {
                        continue;
                    }

                    if (array_key_exists('heading_en', $block['data'])) {
                        unset($block['data']['heading_en']);
                        $changed = true;
                    }
                }
                unset($block);
            }

            if ($changed) {
                DB::table('pages')
                    ->where('id', $page->id)
                    ->update(['blocks' => json_encode($blocksRaw)]);
            }
        }
    }

    /**
     * Reverse is not meaningful — heading_en data has been merged into heading.
     */
    public function down(): void
    {
        // No-op: cannot reliably restore which headings were originally _en values
    }
};
