<?php

use App\Models\Page;
use App\Models\Tile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillPageBlocks();
        $this->backfillTileBlocks();
    }

    public function down(): void
    {
        // No rollback: activation flags are additive and non-destructive.
    }

    private function backfillPageBlocks(): void
    {
        if (! Schema::hasColumn('pages', 'blocks')) {
            return;
        }

        Page::withoutEvents(function (): void {
            Page::query()
                ->select(['id', 'blocks'])
                ->chunkById(50, function ($pages): void {
                    foreach ($pages as $page) {
                        $blocksByLocale = $page->blocks ?? [];

                        if (! is_array($blocksByLocale) || $blocksByLocale === []) {
                            continue;
                        }

                        $changed = false;

                        foreach ($blocksByLocale as $locale => $blocks) {
                            if (! is_array($blocks)) {
                                continue;
                            }

                            $blocksByLocale[$locale] = $this->normalizeBlocks($blocks, $changed);
                        }

                        if ($changed) {
                            $page->forceFill(['blocks' => $blocksByLocale])->save();
                        }
                    }
                });
        });
    }

    private function backfillTileBlocks(): void
    {
        if (! Schema::hasColumn('tiles', 'background_blocks')) {
            return;
        }

        Tile::withoutEvents(function (): void {
            Tile::query()
                ->select(['id', 'background_blocks'])
                ->chunkById(50, function ($tiles): void {
                    foreach ($tiles as $tile) {
                        $blocks = $tile->background_blocks;

                        if (! is_array($blocks) || $blocks === []) {
                            continue;
                        }

                        $changed = false;
                        $blocks = $this->normalizeBlocks($blocks, $changed);

                        if ($changed) {
                            $tile->forceFill(['background_blocks' => $blocks])->save();
                        }
                    }
                });
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBlocks(array $blocks, bool &$changed): array
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockChanged = false;

            if (isset($block['data']) && is_array($block['data'])) {
                if (! array_key_exists('is_active', $block['data'])) {
                    $block['data']['is_active'] = true;
                    $blockChanged = true;
                }
            } elseif (! array_key_exists('is_active', $block)) {
                $block['is_active'] = true;
                $blockChanged = true;
            }

            if ($blockChanged) {
                $blocks[$index] = $block;
                $changed = true;
            }
        }

        return $blocks;
    }
};
