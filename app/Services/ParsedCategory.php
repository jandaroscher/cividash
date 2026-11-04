<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

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
