<?php

namespace Database\Seeders;

use App\Models\Tile;
use App\Services\MediaDownloadService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Seeder for Tiles from dashboard.json.
 *
 * Seeds tiles (kacheln) from the Regensburg dashboard.json file.
 * Supports idempotent upserts, background block transformation, and category relationships.
 */
class TileSeeder extends Seeder
{
    protected MediaDownloadService $mediaDownloadService;

    public function __construct()
    {
        $this->mediaDownloadService = new MediaDownloadService;
    }

    /**
     * Create or update Tile records from parsed tile data and synchronize their category relations.
     *
     * @param  Collection<int, \App\Services\ParsedTile>  $tiles  Collection of parsed tiles to seed.
     * @param  array<string, int>  $categoryIdMap  Map of original category ID to database ID (Handlungsfelder).
     * @param  array<string, int>  $dimensionCategoryMap  Map of dimension key to category ID.
     * @param  array<int, int>  $sdgCategoryMap  Map of original SDG ID to category ID.
     * @return array<string, int> Map of original tile ID to database ID.
     */
    public function run(
        Collection $tiles,
        array $categoryIdMap,
        array $dimensionCategoryMap = [],
        array $sdgCategoryMap = [],
    ): array {
        $tileIdMap = [];

        foreach ($tiles as $parsedTile) {
            $titleDe = $parsedTile->title;

            $tile = Tile::whereJsonContains('title->de', $titleDe)->first();

            $sourceHash = $this->calculateSourceHash($parsedTile);

            if (! $tile) {
                $tile = new Tile;
                $tile->title = [
                    'de' => $parsedTile->title,
                    'en' => $parsedTile->titleEn,
                ];
                $tile->description = [
                    'de' => $parsedTile->description,
                    'en' => $parsedTile->descriptionEn,
                ];
                $iconPath = $this->downloadIcon($parsedTile->icon);
                $tile->icon = $iconPath;
                $tile->position = $parsedTile->position ?? 0;
                $tile->background_blocks = $this->transformBackgroundBlocks($parsedTile);
                $tile->last_synced_at = now();
                $tile->source_hash = $sourceHash;
                $tile->save();
            } else {
                $needsUpdate = false;

                if ($tile->getTranslation('title', 'de') !== $parsedTile->title) {
                    $tile->setTranslation('title', 'de', $parsedTile->title);
                    $needsUpdate = true;
                }
                if ($parsedTile->titleEn !== null && $tile->getTranslation('title', 'en') !== $parsedTile->titleEn) {
                    $tile->setTranslation('title', 'en', $parsedTile->titleEn);
                    $needsUpdate = true;
                }

                if ($parsedTile->description !== null && $tile->getTranslation('description', 'de') !== $parsedTile->description) {
                    $tile->setTranslation('description', 'de', $parsedTile->description);
                    $needsUpdate = true;
                }
                if ($parsedTile->descriptionEn !== null && $tile->getTranslation('description', 'en') !== $parsedTile->descriptionEn) {
                    $tile->setTranslation('description', 'en', $parsedTile->descriptionEn);
                    $needsUpdate = true;
                }

                $newIcon = $this->downloadIcon($parsedTile->icon);
                if ($tile->icon !== $newIcon) {
                    $tile->icon = $newIcon;
                    $needsUpdate = true;
                }
                if ($tile->position !== ($parsedTile->position ?? 0)) {
                    $tile->position = $parsedTile->position ?? 0;
                    $needsUpdate = true;
                }

                $newBackgroundBlocks = $this->transformBackgroundBlocks($parsedTile);
                if ($tile->background_blocks !== $newBackgroundBlocks) {
                    $tile->background_blocks = $newBackgroundBlocks;
                    $needsUpdate = true;
                }

                if ($tile->source_hash !== $sourceHash) {
                    $tile->source_hash = $sourceHash;
                    $tile->last_synced_at = now();
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $tile->save();
                }
            }

            // Attach categories across all groups
            $categoryIds = $this->mapCategoryIds($parsedTile->categoryIds, $categoryIdMap);

            if (! empty($dimensionCategoryMap) && $parsedTile->handlungsdimension) {
                $dimensionCategoryId = $dimensionCategoryMap[$parsedTile->handlungsdimension] ?? null;
                if ($dimensionCategoryId) {
                    $categoryIds[] = $dimensionCategoryId;
                }
            }

            if (! empty($sdgCategoryMap)) {
                $sdgCategoryIds = $this->mapSDGZielIds($parsedTile->sdgZielIds, $sdgCategoryMap);
                $categoryIds = array_merge($categoryIds, $sdgCategoryIds);
            }

            $tile->categories()->sync(array_unique($categoryIds));

            $tileIdMap[$parsedTile->id] = $tile->id;

            if ($this->command) {
                $this->command->info("Tile seeded: {$parsedTile->title} (ID: {$tile->id})");
            }
        }

        return $tileIdMap;
    }

    protected function generateSlug(string $title): string
    {
        return Str::slug($title);
    }

    /**
     * Transform tile content into Fabricator background blocks.
     */
    protected function transformBackgroundBlocks(\App\Services\ParsedTile $tile): ?array
    {
        $blocks = [];
        $validBlockTypes = $this->getValidBlockTypes();

        if (! empty($tile->backgroundText)) {
            $blockType = $this->validateBlockType('intro-text', $validBlockTypes);
            $blockData = [
                'heading' => $tile->title ?? null,
                'text' => $tile->backgroundText,
            ];

            if (! empty($tile->titleEn)) {
                $blockData['heading_en'] = $tile->titleEn;
            }
            if (! empty($tile->backgroundTextEn)) {
                $blockData['text_en'] = $tile->backgroundTextEn;
            }

            $blockData = array_filter($blockData, fn ($value) => $value !== null);

            if (! empty($blockData)) {
                $blocks[] = [
                    'type' => $blockType,
                    'data' => $blockData,
                ];
            }
        }

        if (! empty($tile->sliderData)) {
            $sliderItems = [];
            foreach ($tile->sliderData as $sliderItem) {
                $sliderImagePath = $this->downloadSliderImage($sliderItem['image'] ?? null);
                $item = [
                    'title' => $sliderItem['title'] ?? null,
                    'description' => $sliderItem['text'] ?? null,
                    'image' => $sliderImagePath,
                    'link_url' => $sliderItem['link'] ?? null,
                    'link_text' => null,
                ];

                if (isset($sliderItem['title_en']) && ! empty($sliderItem['title_en'])) {
                    $item['title_en'] = $sliderItem['title_en'];
                }
                if (isset($sliderItem['text_en']) && ! empty($sliderItem['text_en'])) {
                    $item['description_en'] = $sliderItem['text_en'];
                }

                $item = array_filter($item, fn ($value) => $value !== null);

                if (! empty($item)) {
                    $sliderItems[] = $item;
                }
            }

            if (! empty($sliderItems)) {
                $blockType = $this->validateBlockType('slider', $validBlockTypes);
                $blocks[] = [
                    'type' => $blockType,
                    'data' => [
                        'items' => $sliderItems,
                    ],
                ];
            }
        }

        if (! empty($tile->contributionText)) {
            $blockType = $this->validateBlockType('intro-text', $validBlockTypes);
            $blockData = [
                'heading' => 'Beitrag',
                'text' => $tile->contributionText,
            ];

            if (! empty($tile->contributionTextEn)) {
                $blockData['heading_en'] = 'Contribution';
                $blockData['text_en'] = $tile->contributionTextEn;
            }

            $blockData = array_filter($blockData, fn ($value) => $value !== null);

            if (! empty($blockData)) {
                $blocks[] = [
                    'type' => $blockType,
                    'data' => $blockData,
                ];
            }
        }

        return ! empty($blocks) ? $blocks : null;
    }

    /**
     * @return array<string>
     */
    protected function getValidBlockTypes(): array
    {
        $registeredBlocks = config('filament-fabricator.page-blocks.register', []);
        $validTypes = [];

        foreach ($registeredBlocks as $blockClass) {
            if (class_exists($blockClass) && method_exists($blockClass, 'getBlockSchema')) {
                try {
                    $schema = $blockClass::getBlockSchema();
                    if ($schema && method_exists($schema, 'getName')) {
                        $validTypes[] = $schema->getName();
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $validTypes;
    }

    /**
     * @param  array<string>  $validTypes
     */
    protected function validateBlockType(string $blockType, array $validTypes): string
    {
        if (in_array($blockType, $validTypes)) {
            return $blockType;
        }

        $fallbackType = 'intro-text';
        if (in_array($fallbackType, $validTypes)) {
            if ($this->command) {
                $this->command->warn("Block type '{$blockType}' is not registered, falling back to '{$fallbackType}'");
            }

            return $fallbackType;
        }

        if (empty($validTypes)) {
            $errorMessage = "No valid block types registered. Cannot validate block type '{$blockType}'.";
            Log::error($errorMessage, [
                'invalid_block_type' => $blockType,
                'available_types' => $validTypes,
            ]);
            if ($this->command) {
                $this->command->error($errorMessage);
            }
            throw new \RuntimeException($errorMessage);
        }

        $safeFallback = $validTypes[0];
        Log::error("Block type '{$blockType}' and fallback '{$fallbackType}' are not registered, using '{$safeFallback}'", [
            'invalid_block_type' => $blockType,
            'fallback_type' => $fallbackType,
            'available_types' => $validTypes,
            'used_fallback' => $safeFallback,
        ]);
        if ($this->command) {
            $this->command->warn("Block type '{$blockType}' is not registered, and '{$fallbackType}' is also unavailable. Using '{$safeFallback}' as fallback.");
        }

        return $safeFallback;
    }

    protected function calculateSourceHash(\App\Services\ParsedTile $tile): string
    {
        $dataToHash = [
            'id' => $tile->id,
            'title' => $tile->title,
            'title_en' => $tile->titleEn,
            'description' => $tile->description,
            'description_en' => $tile->descriptionEn,
            'position' => $tile->position,
            'icon' => $tile->icon,
            'background_text' => $tile->backgroundText,
            'background_text_en' => $tile->backgroundTextEn,
            'contribution_text' => $tile->contributionText,
            'contribution_text_en' => $tile->contributionTextEn,
            'slider_data' => $tile->sliderData,
            'category_ids' => $tile->categoryIds,
            'handlungsdimension' => $tile->handlungsdimension,
            'sdg_ziel_ids' => $tile->sdgZielIds,
        ];

        return hash('sha256', json_encode($dataToHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function downloadIcon(mixed $iconData): ?string
    {
        $systemUrl = $this->mediaDownloadService->extractSystemUrl($iconData);
        if (empty($systemUrl)) {
            return null;
        }

        $downloadedPath = $this->mediaDownloadService->downloadFile($systemUrl, 'tiles');
        if ($downloadedPath && $this->command) {
            $this->command->info("Downloaded icon: {$downloadedPath}");
        } elseif (! $downloadedPath && $this->command && $systemUrl) {
            $this->command->warn("Failed to download icon: {$systemUrl}");
        }

        return $downloadedPath;
    }

    protected function downloadSliderImage(mixed $imageData): ?string
    {
        $systemUrl = $this->mediaDownloadService->extractSystemUrl($imageData);
        if (empty($systemUrl)) {
            return null;
        }

        $downloadedPath = $this->mediaDownloadService->downloadFile($systemUrl, 'slider');
        if ($downloadedPath && $this->command) {
            $this->command->info("Downloaded slider image: {$downloadedPath}");
        } elseif (! $downloadedPath && $this->command && $systemUrl) {
            $this->command->warn("Failed to download slider image: {$systemUrl}");
        }

        return $downloadedPath;
    }

    /**
     * @param  array<int>  $originalIds
     * @param  array<string, int>  $idMap
     * @return array<int>
     */
    protected function mapCategoryIds(array $originalIds, array $idMap): array
    {
        return collect($originalIds)
            ->map(fn (int $id) => $idMap[$id] ?? null)
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * @param  array<int>  $originalIds
     * @param  array<int, int>  $idMap
     * @return array<int>
     */
    protected function mapSDGZielIds(array $originalIds, array $idMap): array
    {
        return collect($originalIds)
            ->map(fn (int $id) => $idMap[$id] ?? null)
            ->filter()
            ->values()
            ->toArray();
    }
}
