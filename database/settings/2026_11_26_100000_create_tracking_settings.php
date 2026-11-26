<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Support\Facades\DB;
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

    /**
     * The migrator only deletes global rows, so drop every tenant's values directly.
     */
    public function down(): void
    {
        DB::table('settings')->where('group', 'tracking')->delete();
    }
};
