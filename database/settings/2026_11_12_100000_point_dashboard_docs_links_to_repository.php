<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    private const LINKS = [
        'open_source_docs_url' => 'https://github.com/jandaroscher/cividash/tree/main/docs',
        'user_manual_url' => 'https://github.com/jandaroscher/cividash/tree/main/docs/user-guide',
    ];

    /**
     * Hosts of outdated defaults: the example.org placeholder and the developer's
     * company site, which early installs stored instead of the project docs.
     */
    private const OUTDATED_HOSTS = ['example.org', 'www.example.org', 'janda-roscher.de', 'www.janda-roscher.de'];

    /**
     * Points the documentation links on the admin overview to the project docs, in the
     * global and all tenant rows. Only empty, invalid or outdated default values are
     * replaced; links set on purpose (e.g. through the admin API) stay. A theme's
     * JSON can still override these values and is not touched. The payload is
     * compared in PHP because its column type differs per database.
     */
    public function up(): void
    {
        foreach (self::LINKS as $name => $url) {
            DB::table('settings')
                ->where('group', 'dashboard')
                ->where('name', $name)
                ->get(['id', 'payload'])
                ->filter(fn ($row) => $this->isOutdated(json_decode((string) $row->payload)))
                ->each(fn ($row) => DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode($url)]));
        }
    }

    /**
     * One-way: the replaced values were placeholders or wrong links.
     */
    public function down(): void {}

    private function isOutdated(mixed $value): bool
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));

        return ! in_array($scheme, ['http', 'https'], true) || in_array($host, self::OUTDATED_HOSTS, true);
    }
};
