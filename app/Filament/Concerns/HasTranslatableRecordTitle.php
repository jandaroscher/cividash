<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Falls back to the German title in headings and breadcrumbs, so a record that is not
 * translated yet does not show an empty heading in another locale.
 */
trait HasTranslatableRecordTitle
{
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        $title = parent::getRecordTitle($record);

        if (filled($title) || $record === null || ! method_exists($record, 'getTranslation')) {
            return $title;
        }

        return $record->getTranslation(static::getRecordTitleAttribute(), 'de', false) ?: $title;
    }
}
