<?php

namespace App\Services;

/**
 * DTO for parsed category data.
 */
class ParsedCategory
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
    ) {}
}
