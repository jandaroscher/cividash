<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Adds the place for the "developed with love in ..." line below the admin
     * overview. Each installation sets its own; without a value the line is hidden.
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.made_in', null);
    }
};
