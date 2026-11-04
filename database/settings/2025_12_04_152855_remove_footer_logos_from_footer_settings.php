<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Delete the 'footer.footer_logos' settings entry from configuration storage.
     */
    public function up(): void
    {
        $this->migrator->delete('footer.footer_logos');
    }
};
