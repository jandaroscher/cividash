<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and authenticate for Filament tenant context
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();
        if ($tenant) {
            $user->tenants()->sync([$tenant->id]);
            Filament::auth()->login($user);
            Filament::setTenant($tenant);
        }

        // Ensure fixtures directory exists
        if (! File::exists(base_path('tests/Fixtures'))) {
            File::makeDirectory(base_path('tests/Fixtures'), 0755, true);
        }
    }

    public function test_command_seeds_categories_and_tiles(): void
    {
        $jsonPath = base_path('tests/Fixtures/dashboard-minimal.json');

        $this->artisan('dashboard:seed', ['--path' => $jsonPath])
            ->assertSuccessful();

        // Verify categories were created:
        // 3 from handlungsfelder + 3 from HandlungsdimensionSeeder (grün, gerecht, produktiv)
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseHas('categories', []); // At least one category exists

        // Verify tiles were created
        $this->assertDatabaseCount('tiles', 3);
        $this->assertDatabaseHas('tiles', []); // At least one tile exists

        // Verify relationships
        $tile = Tile::whereJsonContains('title->de', 'Bürgerbeteiligung')->first();
        $this->assertNotNull($tile);
        $this->assertGreaterThan(0, $tile->categories()->count());
    }

    public function test_command_dry_run_mode(): void
    {
        $jsonPath = base_path('tests/Fixtures/dashboard-minimal.json');

        $this->artisan('dashboard:seed', [
            '--path' => $jsonPath,
            '--dry-run' => true,
        ])
            ->expectsOutput('Running in DRY-RUN mode - no data will be persisted')
            ->assertSuccessful();

        // Verify no data was persisted
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('tiles', 0);
    }

    public function test_command_handles_invalid_json_path(): void
    {
        $this->artisan('dashboard:seed', ['--path' => '/nonexistent/path.json'])
            ->assertFailed();
    }

    public function test_command_is_transactional(): void
    {
        // This test verifies that if an error occurs, no partial data is saved
        // We'll simulate this by using a malformed JSON that might cause issues
        // Note: The parser will throw an exception for malformed JSON, so transaction should rollback

        $jsonPath = base_path('tests/Fixtures/dashboard-malformed.json');

        // The command should fail, but we need to ensure no partial data
        // Since malformed JSON will fail at parse stage, no data should be inserted
        $this->artisan('dashboard:seed', ['--path' => $jsonPath])
            ->assertFailed();

        // Verify no partial data
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('tiles', 0);
    }

    public function test_command_accepts_path_option(): void
    {
        // Test that the command accepts and uses the --path option when provided
        $jsonPath = base_path('tests/Fixtures/dashboard-minimal.json');

        $this->artisan('dashboard:seed', ['--path' => $jsonPath])
            ->assertSuccessful();

        // Verify data was seeded using the provided path:
        // 3 from handlungsfelder + 3 from HandlungsdimensionSeeder (grün, gerecht, produktiv)
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseCount('tiles', 3);
    }

    public function test_command_displays_summary_in_dry_run(): void
    {
        $jsonPath = base_path('tests/Fixtures/dashboard-minimal.json');

        $this->artisan('dashboard:seed', [
            '--path' => $jsonPath,
            '--dry-run' => true,
        ])
            ->expectsOutput('=== DRY-RUN SUMMARY ===')
            ->expectsOutput('Categories to seed: 3')
            ->expectsOutput('Tiles to seed: 3')
            ->assertSuccessful();
    }

    public function test_command_handles_full_json_successfully(): void
    {
        $jsonPath = base_path('tests/Fixtures/dashboard-full.json');

        $this->artisan('dashboard:seed', ['--path' => $jsonPath])
            ->assertSuccessful();

        // Verify all categories and tiles from full fixture were created:
        // 5 from handlungsfelder + 3 from HandlungsdimensionSeeder (grün, gerecht, produktiv)
        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('tiles', 5);
    }
}
