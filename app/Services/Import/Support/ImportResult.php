<?php

namespace App\Services\Import\Support;

/**
 * Complete outcome of an import run. Serialises to the response envelope
 * for the upload bundle format documented in docs/upload/README.md.
 */
final class ImportResult
{
    /**
     * @param  list<ImportError>  $errors
     * @param  list<ImportWarning>  $warnings
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $status,
        public readonly ImportDiff $diff,
        public array $errors = [],
        public array $warnings = [],
        public readonly int $durationMs = 0,
    ) {}

    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'status' => $this->status,
            'errors' => array_map(fn (ImportError $e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn (ImportWarning $w) => $w->toArray(), $this->warnings),
            'diff' => $this->diff->toArray(),
            'duration_ms' => $this->durationMs,
        ];
    }
}
