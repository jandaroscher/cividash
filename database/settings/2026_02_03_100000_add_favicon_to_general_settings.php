<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Register the "general.favicon" setting with a null initial value.
     *
     * Creates the settings key `general.favicon` and initializes it to `null`.
     */
    public function up(): void
    {
        $this->migrator->add('general.favicon', null);
    }
};
