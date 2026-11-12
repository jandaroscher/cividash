<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Stored CORE API URLs ending in "/context/ngsi-ld" were seeded from the old
     * default and miss the NGSI-LD version: CORE rewrites /context/ngsi-ld/* to
     * Stellio's /ngsi-ld/*, so every entity call returned 404. Appends "/v1" in
     * the global and all tenant rows; other URLs (custom, SensorThings) stay.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'integration')
            ->where('name', 'api_url')
            ->get(['id', 'payload'])
            ->each(function ($row) {
                $url = json_decode($row->payload);
                if (is_string($url) && preg_match('#/context/ngsi-ld/?$#', $url)) {
                    DB::table('settings')->where('id', $row->id)
                        ->update(['payload' => json_encode(rtrim($url, '/').'/v1')]);
                }
            });
    }

    /**
     * One-way: the URL without "/v1" never worked against CORE.
     */
    public function down(): void {}
};
