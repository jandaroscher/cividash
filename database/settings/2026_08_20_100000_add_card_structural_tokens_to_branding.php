<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Structural card tokens.
     * Nullable so existing tenants keep the CSS defaults in app.css
     * (square corners, no border) until a tenant explicitly opts in.
     */
    public function up(): void
    {
        $this->migrator->add('branding.card_radius', null);
        $this->migrator->add('branding.card_border_width', null);
    }
};
