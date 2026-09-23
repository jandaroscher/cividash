<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_aborts_before_deleting_when_no_source_json_is_available(): void
    {
        $this->assertResetKeepsDataWith(storage_path('framework/testing/missing-dashboard.json'));
    }

    public function test_aborts_before_deleting_when_the_local_json_is_malformed(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dashboard');
        file_put_contents($path, '{"kacheln": [');

        try {
            $this->assertResetKeepsDataWith($path);
        } finally {
            unlink($path);
        }
    }

    private function assertResetKeepsDataWith(string $jsonPath): void
    {
        config([
            'seeding.dashboard_json_url' => null,
            'seeding.default_json_path' => $jsonPath,
        ]);

        $tenant = Tenant::factory()->create(['slug' => 'stadt-regensburg']);
        $tile = Tile::factory()->create(['tenant_id' => $tenant->id]);

        $this->artisan('dashboard:reset', ['--force' => true])->assertFailed();

        $this->assertDatabaseHas('tiles', ['id' => $tile->id]);
    }
}
