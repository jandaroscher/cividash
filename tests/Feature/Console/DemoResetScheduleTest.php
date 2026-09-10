<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

class DemoResetScheduleTest extends TestCase
{
    /**
     * routes/console.php only registers Schedule::command() calls once per process
     * (Laravel's console kernel loads it lazily and caches the Schedule singleton),
     * so a fresh Schedule instance is bound and the file is re-required here to
     * observe the effect of toggling the config flag.
     */
    private function loadScheduleEvents(): array
    {
        $schedule = new Schedule(app());
        app()->instance(Schedule::class, $schedule);
        Facade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        return array_map(fn ($event) => $event->command, $schedule->events());
    }

    public function test_reset_schedule_is_not_registered_by_default(): void
    {
        Config::set('dashboard.demo_reset', false);

        $commands = $this->loadScheduleEvents();

        $this->assertTrue(
            collect($commands)->every(fn ($command) => ! str_contains((string) $command, 'dashboard:reset'))
        );
    }

    public function test_reset_schedule_is_registered_when_flag_enabled(): void
    {
        Config::set('dashboard.demo_reset', true);

        $commands = $this->loadScheduleEvents();

        $this->assertTrue(
            collect($commands)->contains(fn ($command) => str_contains((string) $command, 'dashboard:reset'))
        );
    }
}
