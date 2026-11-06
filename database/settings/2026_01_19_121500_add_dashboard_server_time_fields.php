<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Register the dashboard.show_server_time setting with its default value.
     *
     * Adds the 'dashboard.show_server_time' setting with the boolean value `true`.
     */
    public function up(): void
    {
        $this->migrator->add('dashboard.show_server_time', true);
    }
};
