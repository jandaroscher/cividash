<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Rules;

use App\Models\Media;
use App\Services\Media\MediaLibrary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Library ids in a field value (a single reference, a JSON object of one per locale,
 * or block and repeater data) must belong to the current dashboard's media library.
 * Paths are not checked here.
 */
class MediaReference implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // A localized value arrives as a JSON string with one reference per locale.
        $decoded = is_string($value) ? json_decode($value, true) : null;
        $ids = is_array($decoded)
            ? array_values(array_unique(array_map('intval', array_filter($decoded, MediaLibrary::isId(...)))))
            : MediaLibrary::idsIn($value);

        if ($ids !== [] && Media::query()->whereKey($ids)->count() !== count($ids)) {
            $fail(__('filament.media.unknown_reference'));
        }
    }
}
