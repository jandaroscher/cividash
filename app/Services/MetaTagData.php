<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services;

class MetaTagData
{
    /**
     * @param  array<int, array{lang: string, url: string}>  $hreflangLinks
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $image,
        public readonly string $canonicalUrl,
        public readonly string $ogType,
        public readonly string $locale,
        public readonly array $hreflangLinks,
        public readonly ?string $siteName,
        public readonly ?string $imageAlt = null,
        public readonly ?int $imageWidth = null,
        public readonly ?int $imageHeight = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'image_alt' => $this->imageAlt,
            'image_width' => $this->imageWidth,
            'image_height' => $this->imageHeight,
            'canonical_url' => $this->canonicalUrl,
            'og_type' => $this->ogType,
            'locale' => $this->locale,
            'hreflang_links' => $this->hreflangLinks,
            'site_name' => $this->siteName,
        ];
    }
}
