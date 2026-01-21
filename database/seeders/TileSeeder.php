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
        $this->mediaDownloadService = new MediaDownloadService();
    }

    /**
     * Create or update Tile records from parsed tile data and synchronize their category and SDG relations.
     *
     * Processes each ParsedTile: finds or creates a Tile by German title, updates localized titles/descriptions,
     * downloads and sets media, transforms background blocks, assigns handlungsdimension, syncs categories (including
     * handlungsdimension- and SDG-derived category mappings) and legacy SDG relations, and returns a mapping of
     * original tile IDs to database IDs.
     *
     * @param Collection<int, \App\Services\ParsedTile> $tiles Collection of parsed tiles to seed.
     * @param array<string, int> $categoryIdMap Map of original category ID to database ID.
     * @param array<string, int> $handlungsdimensionCategoryMap Map of handlungsdimension key to category ID to attach.
     * @param array<int, int> $sdgZielCategoryMap Map of original SDG ID to category ID to attach.
     * @param array<string, int> $handlungsdimensionIdMap Map of handlungsdimension key to database ID.
     * @param array<int, int> $sdgZielIdMap Map of original SDG ID to database ID for legacy SDG relation syncing.
     * @return array<string, int> Map of original tile ID to database ID.
     */
    public function run(
        Collection $tiles,
        array $categoryIdMap,
        array $handlungsdimensionCategoryMap = [],
        array $sdgZielCategoryMap = [],
        array $handlungsdimensionIdMap = [],
        array $sdgZielIdMap = []
    ): array
    {
        $tileIdMap = [];

        foreach ($tiles as $parsedTile) {
            $titleDe = $parsedTile->title;

            // For translatable fields, search by DE value
            $tile = Tile::whereJsonContains('title->de', $titleDe)->first();

            // Calculate source hash from parsed tile data
            $sourceHash = $this->calculateSourceHash($parsedTile);

            if (! $tile) {
                $tile = new Tile();
                $tile->title = [
                    'de' => $parsedTile->title,
                    'en' => $parsedTile->titleEn,
                ];
                $tile->description = [
                    'de' => $parsedTile->description,
                    'en' => $parsedTile->descriptionEn,
                ];
                // Download icon if available
                $iconPath = $this->downloadIcon($parsedTile->icon);
                $tile->icon = $iconPath;
                $tile->position = $parsedTile->position ?? 0;
                $tile->background_blocks = $this->transformBackgroundBlocks($parsedTile);
                // Set handlungsdimension_id if available
                if ($parsedTile->handlungsdimension && isset($handlungsdimensionIdMap[$parsedTile->handlungsdimension])) {
                    $tile->handlungsdimension_id = $handlungsdimensionIdMap[$parsedTile->handlungsdimension];
                }
                $tile->last_synced_at = now();
                $tile->source_hash = $sourceHash;
                $tile->save();
            } else {
                // Update only if values changed (idempotent)
                $needsUpdate = false;

                // Update title (preserve EN if new is null)
                if ($tile->getTranslation('title', 'de') !== $parsedTile->title) {
                    $tile->setTranslation('title', 'de', $parsedTile->title);
                    $needsUpdate = true;
                }
                if ($parsedTile->titleEn !== null && $tile->getTranslation('title', 'en') !== $parsedTile->titleEn) {
                    $tile->setTranslation('title', 'en', $parsedTile->titleEn);
                    $needsUpdate = true;
                }

                // Update description (preserve EN if new is null)
                if ($parsedTile->description !== null && $tile->getTranslation('description', 'de') !== $parsedTile->description) {
                    $tile->setTranslation('description', 'de', $parsedTile->description);
                    $needsUpdate = true;
                }
                if ($parsedTile->descriptionEn !== null && $tile->getTranslation('description', 'en') !== $parsedTile->descriptionEn) {
                    $tile->setTranslation('description', 'en', $parsedTile->descriptionEn);
                    $needsUpdate = true;
                }

                // Update other fields
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

                // Update handlungsdimension_id if changed
                $newHandlungsdimensionId = null;
                if ($parsedTile->handlungsdimension && isset($handlungsdimensionIdMap[$parsedTile->handlungsdimension])) {
                    $newHandlungsdimensionId = $handlungsdimensionIdMap[$parsedTile->handlungsdimension];
                }
                if ($tile->handlungsdimension_id !== $newHandlungsdimensionId) {
                    $tile->handlungsdimension_id = $newHandlungsdimensionId;
                    $needsUpdate = true;
                }

                // Update source hash and last_synced_at if data changed
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

            if (! empty($handlungsdimensionCategoryMap) && $parsedTile->handlungsdimension) {
                $dimensionCategoryId = $handlungsdimensionCategoryMap[$parsedTile->handlungsdimension] ?? null;
                if ($dimensionCategoryId) {
                    $categoryIds[] = $dimensionCategoryId;
                }
            }

            if (! empty($sdgZielCategoryMap)) {
                $sdgZielIds = $this->mapSDGZielIds($parsedTile->sdgZielIds, $sdgZielCategoryMap);
                $categoryIds = array_merge($categoryIds, $sdgZielIds);
            }

            $tile->categories()->sync(array_unique($categoryIds));

            // Keep legacy SDG relations in sync for backwards compatibility
            if (! empty($sdgZielIdMap)) {
                $sdgZielIds = $this->mapSDGZielIds($parsedTile->sdgZielIds, $sdgZielIdMap);
                $tile->sdgZiele()->sync($sdgZielIds);
            }

            // Store mapping of original ID to database ID
            $tileIdMap[$parsedTile->id] = $tile->id;

            if ($this->command) {
                $this->command->info("Tile seeded: {$parsedTile->title} (ID: {$tile->id})");
            }
        }

        return $tileIdMap;
    }

    /**
     * Generate a slug from the tile title.
     *
     * @param string $title
     * @return string
     */
    protected function generateSlug(string $title): string
    {
        return Str::slug($title);
    }

    /**
     * Transform tile content into Fabricator background blocks.
     * Validates blocks against the registry and falls back to IntroTextBlock if needed.
     * Includes both German (DE) and English (EN) translations when available.
     *
     * @param \App\Services\ParsedTile $tile
     * @return array|null
     */
    protected function transformBackgroundBlocks(\App\Services\ParsedTile $tile): ?array
    {
        $blocks = [];
        $validBlockTypes = $this->getValidBlockTypes();

        // Transform background_text into IntroTextBlock
        if (! empty($tile->backgroundText)) {
            $blockType = $this->validateBlockType('intro-text', $validBlockTypes);
            $blockData = [
                'heading' => $tile->title ?? null,
                'text' => $tile->backgroundText,
            ];

            // Add English translations if available
            if (! empty($tile->titleEn)) {
                $blockData['heading_en'] = $tile->titleEn;
            }
            if (! empty($tile->backgroundTextEn)) {
                $blockData['text_en'] = $tile->backgroundTextEn;
            }

            // Remove null values to keep the array clean
            $blockData = array_filter($blockData, fn ($value) => $value !== null);

            if (! empty($blockData)) {
                $blocks[] = [
                    'type' => $blockType,
                    'data' => $blockData,
                ];
            }
        }

        // Transform slider data into SliderBlock
        if (! empty($tile->sliderData)) {
            $sliderItems = [];
            foreach ($tile->sliderData as $sliderItem) {
                // Download slider image if available
                $sliderImagePath = $this->downloadSliderImage($sliderItem['image'] ?? null);
                $item = [
                    'title' => $sliderItem['title'] ?? null,
                    'description' => $sliderItem['text'] ?? null,
                    'image' => $sliderImagePath,
                    'link_url' => $sliderItem['link'] ?? null,
                    'link_text' => null,
                ];

                // Add English translations for slider items if available
                // Note: Slider data structure may vary, check for _en suffix fields
                if (isset($sliderItem['title_en']) && ! empty($sliderItem['title_en'])) {
                    $item['title_en'] = $sliderItem['title_en'];
                }
                if (isset($sliderItem['text_en']) && ! empty($sliderItem['text_en'])) {
                    $item['description_en'] = $sliderItem['text_en'];
                }

                // Remove null values to keep the array clean
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

        // Transform contribution_text into IntroTextBlock
        if (! empty($tile->contributionText)) {
            $blockType = $this->validateBlockType('intro-text', $validBlockTypes);
            $blockData = [
                'heading' => 'Beitrag',
                'text' => $tile->contributionText,
            ];

            // Add English translations if available
            if (! empty($tile->contributionTextEn)) {
                $blockData['heading_en'] = 'Contribution';
                $blockData['text_en'] = $tile->contributionTextEn;
            }

            // Remove null values to keep the array clean
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
     * Get list of valid block types from Fabricator registry.
     *
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
                    // Skip invalid blocks
                    continue;
                }
            }
        }

        return $validTypes;
    }

    /**
     * Validate block type against registry, fallback to safe option if invalid.
     *
     * @param string $blockType
     * @param array<string> $validTypes
     * @return string
     */
    protected function validateBlockType(string $blockType, array $validTypes): string
    {
        if (in_array($blockType, $validTypes)) {
            return $blockType;
        }

        // Check if default fallback 'intro-text' exists in valid types
        $fallbackType = 'intro-text';
        if (in_array($fallbackType, $validTypes)) {
            if ($this->command) {
                $this->command->warn("Block type '{$blockType}' is not registered, falling back to '{$fallbackType}'");
            }
            return $fallbackType;
        }

        // Fallback 'intro-text' is also not registered, use first valid type as safe fallback
        if (empty($validTypes)) {
            // No valid types available - this is a critical error
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

        // Use first valid type as safe fallback
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

    /**
     * Calculate SHA256 hash of the source data for change detection.
     *
     * @param \App\Services\ParsedTile $tile
     * @return string
     */
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

    /**
     * Download icon and return local path.
     *
     * @param mixed $iconData
     * @return string|null
     */
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

    /**
     * Download slider image and return local path.
     *
     * @param mixed $imageData
     * @return string|null
     */
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
     * Map original category IDs to database IDs.
     *
     * @param array<int> $originalIds
     * @param array<string, int> $idMap
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
     * Map original SDG-Ziel IDs to database IDs.
     *
     * @param array<int> $originalIds
     * @param array<int, int> $idMap
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
