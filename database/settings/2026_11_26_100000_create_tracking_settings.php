<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Tracking stays off for every tenant until an admin enters a Matomo instance.
     */
    public function up(): void
    {
        $this->migrator->add('tracking.matomo_url', null);
        $this->migrator->add('tracking.matomo_site_id', null);
    }

    public function down(): void
    {
        $this->migrator->delete('tracking.matomo_url');
        $this->migrator->delete('tracking.matomo_site_id');
    }
};
