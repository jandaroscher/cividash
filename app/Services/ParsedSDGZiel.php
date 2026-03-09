<?php

namespace App\Services;

/**
 * DTO for parsed SDG goal (SDG-Ziel) data.
 */
class ParsedSDGZiel
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $number, // 1-17
    ) {}
}
