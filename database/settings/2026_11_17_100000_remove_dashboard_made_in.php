<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * The "developed with love in ..." line names where the product is built, so it is
     * fixed text now. Drops the place that earlier builds stored, for every tenant.
     */
    public function up(): void
    {
        DB::table('settings')->where('group', 'dashboard')->where('name', 'made_in')->delete();
    }

    /**
     * Earlier builds require the property, so a rollback re-adds it empty.
     */
    public function down(): void
    {
        $this->migrator->add('dashboard.made_in', null);
    }
};
