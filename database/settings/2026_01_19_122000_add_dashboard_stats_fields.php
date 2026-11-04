<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds the dashboard "made with" text setting to the settings store.
     *
     * Registers the 'dashboard.made_with_text' setting without a default text.
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.made_with_text', null);
    }
};
