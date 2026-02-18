<?php

namespace App\Services\Content;

use App\Models\Page;

class FabricatorPageTransformer
{
    public function __construct(
        protected BlockTransformer $blockTransformer
    ) {}

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
        $blocks = $page->getTranslation('blocks', $locale, false);
        $transformedBlocks = $this->blockTransformer->transform(is_array($blocks) ? $blocks : []);

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
            $blocks = $page->getTranslation('blocks', $locale, false);
            $transformedBlocks = $this->blockTransformer->transform(is_array($blocks) ? $blocks : []);

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
