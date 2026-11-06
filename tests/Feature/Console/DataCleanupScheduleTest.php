<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DataCleanupScheduleTest extends TestCase
{
    public static function cleanupCommands(): array
    {
        return [
            'expired API tokens' => ['sanctum:prune-expired --hours=24'],
            'expired password reset tokens' => ['auth:clear-resets'],
            'failed jobs older than 90 days' => ['queue:prune-failed --hours=2160'],
        ];
    }

    #[DataProvider('cleanupCommands')]
    public function test_cleanup_command_is_scheduled_daily(string $command): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression(
            '/^\s*\d+\s+\d+\s+\*\s+\*\s+\*\s+php artisan '.preg_quote($command, '/').'\s/m',
            Artisan::output()
        );
    }
}
