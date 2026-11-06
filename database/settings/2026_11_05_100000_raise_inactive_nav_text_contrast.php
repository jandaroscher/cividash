<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Replaces the old default inactive navigation colour gray-400 (#9CA3AF, 2.5:1 on
     * white, below WCAG 2.1 AA) with gray-500 (#6B7280, 4.8:1) in the global and all
     * tenant rows. Other colours stay. Values set in a theme's JSON are not touched.
     * The payload is compared in PHP because its column type differs per database
     * (json on PostgreSQL, text on SQLite).
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'branding')
            ->where('name', 'nav_text_color_inactive')
            ->get(['id', 'payload'])
            ->filter(fn ($row) => strtoupper((string) json_decode($row->payload)) === '#9CA3AF')
            ->each(fn ($row) => DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode('#6B7280')]));
    }

    /**
     * One-way: #6B7280 is also the new form default and a valid custom choice, so
     * reverting it would lower contrast for rows this migration never touched.
     */
    public function down(): void {}
};
