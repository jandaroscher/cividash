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
     * tenant rows. Custom colours stay.
     */
    public function up(): void
    {
        $this->replace('#9CA3AF', '#6B7280');
    }

    public function down(): void
    {
        $this->replace('#6B7280', '#9CA3AF');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('settings')
            ->where('group', 'branding')
            ->where('name', 'nav_text_color_inactive')
            ->whereRaw('UPPER(payload) = ?', [json_encode($from)])
            ->update(['payload' => json_encode($to)]);
    }
};
