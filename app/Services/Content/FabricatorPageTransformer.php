<?php

namespace App\Services\Content;

use App\Models\Page;

class FabricatorPageTransformer
{
    /**
     * Transform a Fabricator Page model into a stable API payload.
     */
    public function transform(Page $page): array
    {
        $locale = app()->getLocale();

        $tenantId = null;
        $tenantSlug = null;

        if (function_exists('tenant')) {
            try {
                $tenant = tenant();
                if ($tenant) {
                    $tenantId = $tenant->id ?? null;
                    $tenantSlug = $tenant->slug ?? null;
                }
            } catch (\Throwable $e) {
                // Ignore tenancy resolution errors and fall back to null
            }
        }

        // Use translatable fields
        $title = $page->getTranslation('title', $locale, false);
        $slug = $page->getTranslation('slug', $locale, false);
        $metaTitle = $page->getTranslation('meta_title', $locale, false);
        $metaDescription = $page->getTranslation('meta_description', $locale, false);
        $blocks = $page->getTranslation('blocks', $locale, false) ?? [];

        // Transform blocks
        $transformedBlocks = collect($blocks)->map(function (array $block): array {
            // Filament Builder can store blocks in two formats:
            // 1. ['type' => 'hero', 'data' => [...]] (with data key)
            // 2. ['type' => 'hero', 'field1' => 'value1', ...] (data directly in block)
            $type = $block['type'] ?? $block['handle'] ?? null;
            
            // If 'data' key exists, use it; otherwise, extract all non-metadata keys as props
            if (isset($block['data']) && is_array($block['data'])) {
                $props = $block['data'];
            } else {
                // Remove metadata keys and use the rest as props
                $props = array_diff_key($block, array_flip(['type', 'handle', 'id', 'uuid']));
            }
            
            return [
                'type' => $type,
                'props' => $props,
            ];
        })->filter(fn (array $block) => ! empty($block['type']))->values()->toArray();

        return [
            'id' => $page->id,
            'slug' => $slug,
            'title' => $title,
            'layout' => $page->layout,
            'locale' => $locale,
            'tenant' => [
                'id' => $tenantId,
                'slug' => $tenantSlug,
            ],
            'meta' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'image' => $page->og_image_url ?? null,
            ],
            'blocks' => $transformedBlocks,
            'updated_at' => optional($page->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Transform a Fabricator Page model into a stable API payload for all locales.
     * Returns both DE and EN translations.
     */
    public function transformAllLocales(Page $page): array
    {
        $tenantId = null;
        $tenantSlug = null;

        if (function_exists('tenant')) {
            try {
                $tenant = tenant();
                if ($tenant) {
                    $tenantId = $tenant->id ?? null;
                    $tenantSlug = $tenant->slug ?? null;
                }
            } catch (\Throwable $e) {
                // Ignore tenancy resolution errors and fall back to null
            }
        }

        $result = [
            'id' => $page->id,
            'layout' => $page->layout,
            'tenant' => [
                'id' => $tenantId,
                'slug' => $tenantSlug,
            ],
            'updated_at' => optional($page->updated_at)->toIso8601String(),
        ];

        // Transform for both locales
        foreach (['de', 'en'] as $locale) {
            $title = $page->getTranslation('title', $locale, false);
            $slug = $page->getTranslation('slug', $locale, false);
            $metaTitle = $page->getTranslation('meta_title', $locale, false);
            $metaDescription = $page->getTranslation('meta_description', $locale, false);
            $blocks = $page->getTranslation('blocks', $locale, false) ?? [];

            // Transform blocks
            $transformedBlocks = collect($blocks)->map(function (array $block): array {
                $type = $block['type'] ?? $block['handle'] ?? null;
                
                if (isset($block['data']) && is_array($block['data'])) {
                    $props = $block['data'];
                } else {
                    $props = array_diff_key($block, array_flip(['type', 'handle', 'id', 'uuid']));
                }
                
                return [
                    'type' => $type,
                    'props' => $props,
                ];
            })->filter(fn (array $block) => ! empty($block['type']))->values()->toArray();

            $result[$locale] = [
                'slug' => $slug,
                'title' => $title,
                'meta' => [
                    'title' => $metaTitle,
                    'description' => $metaDescription,
                    'image' => $page->og_image_url ?? null,
                ],
                'blocks' => $transformedBlocks,
            ];
        }

        return $result;
    }
}


