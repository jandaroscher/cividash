<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Import\Support;

final readonly class ImportWarning
{
    public function __construct(
        public string $path,
        public string $code,
        public string $message,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
