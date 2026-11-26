<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Concerns;

/**
 * Edit pages accept ?activeLocale=xx so links (e.g. from the frontend) open the matching translation.
 */
trait OpensInLinkedLocale
{
    protected function openLinkedLocale(): void
    {
        $locale = request()->query('activeLocale');

        if (! $locale || ! in_array($locale, $this->getTranslatableLocales()) || $locale === $this->activeLocale) {
            return;
        }

        // Same steps as switching the locale in the UI, so the other locale's form data is swapped in.
        $this->updatingActiveLocale();
        $this->activeLocale = $locale;
        $this->updatedActiveLocale();
    }
}
