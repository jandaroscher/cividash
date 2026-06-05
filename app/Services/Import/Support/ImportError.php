<?php

namespace App\Services\Import\Support;

/**
 * Structured error entry for the import response. Shape is part of the
 * public Admin-API contract (see docs/upload/README.md for the upload bundle format).
 */
final readonly class ImportError
{
    public function __construct(
        public string $path,
        public string $code,
        public string $message,
        public ?int $row = null,
    ) {}

    public function toArray(): array
    {
        return [
            'row' => $this->row,
            'path' => $this->path,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
