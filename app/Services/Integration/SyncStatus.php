<?php

namespace App\Services\Integration;

use Carbon\CarbonInterface;

class SyncStatus
{
    public function __construct(
        public readonly ?CarbonInterface $lastSyncedAt = null,
        public readonly ?SyncResult $lastResult = null,
        public readonly bool $isConfigured = false,
        public readonly bool $isConnected = false,
    ) {}

    public function hasEverSynced(): bool
    {
        return $this->lastSyncedAt !== null;
    }

    public function toArray(): array
    {
        return [
            'last_synced_at' => $this->lastSyncedAt?->toIso8601String(),
            'last_result' => $this->lastResult?->toArray(),
            'is_configured' => $this->isConfigured,
            'is_connected' => $this->isConnected,
        ];
    }
}
