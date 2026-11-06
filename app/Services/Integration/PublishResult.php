<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Integration;

/**
 * Outcome of publishing a single Tile to the external broker.
 */
class PublishResult
{
    public function __construct(
        public readonly bool $published,
        public readonly bool $skipped,
        public readonly string $externalId,
    ) {}

    public static function published(string $externalId): self
    {
        return new self(published: true, skipped: false, externalId: $externalId);
    }

    public static function skipped(string $externalId): self
    {
        return new self(published: false, skipped: true, externalId: $externalId);
    }
}
