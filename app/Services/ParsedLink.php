<?php

namespace App\Services;

/**
 * DTO for parsed relationship link.
 */
class ParsedLink
{
    public function __construct(
        public readonly int $tileId,
        public readonly int $categoryId,
    ) {}
}
