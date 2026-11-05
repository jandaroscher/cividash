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
            'expired API tokens' => ['sanctum:prune-expired'],
            'expired password reset tokens' => ['auth:clear-resets'],
            'failed jobs older than 90 days' => ['queue:prune-failed'],
        ];
    }

    #[DataProvider('cleanupCommands')]
    public function test_cleanup_command_is_scheduled_daily(string $command): void
    {
        $this->assertArrayHasKey($command, Artisan::all());

        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression(
            '/^\s*\d+\s+\d+\s+\*\s+\*\s+\*\s+php artisan '.preg_quote($command, '/').'\b/m',
            Artisan::output()
        );
    }
}
