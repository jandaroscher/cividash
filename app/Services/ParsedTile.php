<?php

namespace App\Services;

/**
 * DTO for parsed tile data.
 */
class ParsedTile
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $titleEn,
        public readonly ?string $description,
        public readonly ?string $descriptionEn,
        public readonly ?int $position,
        public readonly mixed $icon,
        public readonly ?string $backgroundText,
        public readonly ?string $backgroundTextEn,
        public readonly ?string $contributionText,
        public readonly ?string $contributionTextEn,
        public readonly array $sliderData,
        public readonly array $categoryIds,
        public readonly array $metricIds,
        public readonly ?string $handlungsdimension, // "grün", "gerecht", "produktiv"
        public readonly array $sdgZielIds,
    ) {}
}
