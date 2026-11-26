<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Optional visitor statistics per dashboard. Empty by default, so no tracking
 * script is loaded until an admin enters the operator's Matomo instance.
 */
class TrackingSettings extends Settings
{
    public ?string $matomo_url = null;

    public ?string $matomo_site_id = null;

    public static function group(): string
    {
        return 'tracking';
    }

    /**
     * Configuration handed to the SPA, or null when tracking is off. The
     * `provider` key selects the frontend adapter in resources/js/lib/tracking.
     *
     * @return array{provider: string, url: string, site_id: string}|null
     */
    public function frontendConfig(): ?array
    {
        if (blank($this->matomo_url) || blank($this->matomo_site_id)) {
            return null;
        }

        return [
            'provider' => 'matomo',
            'url' => rtrim($this->matomo_url, '/').'/',
            'site_id' => $this->matomo_site_id,
        ];
    }
}
