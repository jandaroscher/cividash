<?php

namespace App\Services\Integration;

class SyncResult
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $deleted = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly array $errors = [],
        public readonly bool $dryRun = false,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->deleted + $this->skipped + $this->failed;
    }

    public function hasErrors(): bool
    {
        return $this->failed > 0 || ! empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'deleted' => $this->deleted,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'total' => $this->total(),
            'errors' => $this->errors,
            'dry_run' => $this->dryRun,
        ];
    }
}
