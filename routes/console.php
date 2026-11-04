<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('dashboard.demo_reset')) {
    Schedule::command('dashboard:reset --force')
        ->daily()
        ->at('03:00')
        ->environments(['production']);
}

Schedule::command('integration:sync-civitas')
    ->cron(config('integrations.civitas.sync.schedule') === 'hourly' ? '0 * * * *' : '0 4 * * *')
    ->environments(['production'])
    ->withoutOverlapping();
