<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

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
