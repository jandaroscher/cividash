<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

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
