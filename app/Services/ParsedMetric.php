<?php

namespace App\Services;

/**
 * DTO for parsed metric (Kennzahl) data.
 */
class ParsedMetric
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $title,
        public readonly ?string $titleEn,
        public readonly ?string $unit,
        public readonly mixed $icon,
        public readonly array $years, // [{year: int, value: mixed}]
        public readonly ?string $indicator_type = null,
    ) {}
}
