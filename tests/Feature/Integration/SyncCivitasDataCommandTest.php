<?php

namespace Tests\Feature\Integration;

use App\Contracts\Integration\SyncServiceInterface;
use App\Models\Tenant;
use App\Services\Integration\SyncResult;
use App\Services\Integration\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncCivitasDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_warns_and_returns_success_when_integration_disabled(): void
    {
        config(['integrations.civitas.enabled' => false]);

        $this->artisan('integration:sync-civitas')
            ->expectsOutputToContain('CIVITAS integration is disabled')
            ->assertExitCode(0);
    }

    public function test_returns_failure_when_tenant_slug_not_found(): void
    {
        config(['integrations.civitas.enabled' => true]);

        $this->artisan('integration:sync-civitas', ['--tenant' => 'does-not-exist'])
            ->expectsOutputToContain("No tenant found with slug 'does-not-exist'.")
            ->assertExitCode(1);
    }

    public function test_outputs_table_and_succeeds_on_clean_sync(): void
    {
        config(['integrations.civitas.enabled' => true]);

        $tenant = Tenant::where('slug', 'default')->first();

        $this->mockSyncService(new SyncResult(created: 3, updated: 1, skipped: 2));

        $this->artisan('integration:sync-civitas', ['--tenant' => $tenant->slug])
            ->expectsOutputToContain($tenant->slug)
            ->expectsTable(
                ['Created', 'Updated', 'Deleted', 'Skipped', 'Failed', 'Dry Run'],
                [[3, 1, 0, 2, 0, 'No']],
            )
            ->assertExitCode(0);
    }

    public function test_reports_failure_counts_and_returns_failure_on_errors(): void
    {
        config(['integrations.civitas.enabled' => true]);

        $tenant = Tenant::where('slug', 'default')->first();

        $this->mockSyncService(new SyncResult(
            created: 1,
            failed: 2,
            errors: ['Entity urn:foo could not be mapped', 'Entity urn:bar threw'],
        ));

        $this->artisan('integration:sync-civitas', ['--tenant' => $tenant->slug])
            ->expectsOutputToContain('Entity urn:foo could not be mapped')
            ->expectsOutputToContain('Entity urn:bar threw')
            ->assertExitCode(1);
    }

    public function test_returns_failure_when_sync_service_throws(): void
    {
        config(['integrations.civitas.enabled' => true]);

        $tenant = Tenant::where('slug', 'default')->first();

        $mock = $this->mock(SyncServiceInterface::class);
        $mock->shouldReceive('syncAll')
            ->once()
            ->andThrow(new \RuntimeException('broker unreachable'));

        $this->artisan('integration:sync-civitas', ['--tenant' => $tenant->slug])
            ->expectsOutputToContain('broker unreachable')
            ->assertExitCode(1);
    }

    /**
     * Bind a fake SyncService that returns the given result from syncAll().
     */
    private function mockSyncService(SyncResult $result): void
    {
        $mock = $this->mock(SyncServiceInterface::class);
        $mock->shouldReceive('getLastSyncStatus')
            ->andReturn(new SyncStatus(isConfigured: true))
            ->byDefault();
        $mock->shouldReceive('syncAll')
            ->once()
            ->andReturn($result);
    }
}
