<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Points the documentation links on the admin overview to the project's docs.
     * Earlier installs stored a placeholder or another organisation's URL. The settings
     * page for these values is not in the admin navigation, so every row is overwritten;
     * the admin API can still set custom links afterwards.
     */
    public function up(): void
    {
        $links = [
            'open_source_docs_url' => 'https://github.com/jandaroscher/cividash/tree/main/docs',
            'user_manual_url' => 'https://github.com/jandaroscher/cividash/tree/main/docs/user-guide',
        ];

        foreach ($links as $name => $url) {
            DB::table('settings')
                ->where('group', 'dashboard')
                ->where('name', $name)
                ->update(['payload' => json_encode($url)]);
        }
    }

    /**
     * One-way: the previous values were placeholders or wrong links.
     */
    public function down(): void {}
};
