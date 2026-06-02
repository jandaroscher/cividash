<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('dashboard:reset --force')
    ->daily()
    ->at('03:00')
    ->environments(['production']);

Schedule::command('integration:sync-civitas')
    ->cron(config('integrations.civitas.sync.schedule') === 'hourly' ? '0 * * * *' : '0 4 * * *')
    ->environments(['production'])
    ->withoutOverlapping();
