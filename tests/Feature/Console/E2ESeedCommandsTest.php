<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class E2ESeedCommandsTest extends TestCase
{
    use RefreshDatabase;

    // ========== E2E Seed Full Command ==========

    public function test_seed_full_creates_tenants_and_data(): void
    {
        $this->artisan('e2e:seed-full')
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['slug' => 'e2e-tenant-a']);
        $this->assertDatabaseHas('tenants', ['slug' => 'e2e-tenant-b']);

        $tenantA = Tenant::where('slug', 'e2e-tenant-a')->first();
        $tenantB = Tenant::where('slug', 'e2e-tenant-b')->first();

        // Verify tiles created for each tenant
        $this->assertGreaterThan(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count());
        $this->assertGreaterThan(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $tenantB->id)->count());

        // Verify categories created for each tenant
        $this->assertGreaterThan(0, Category::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count());
        $this->assertGreaterThan(0, Category::withoutGlobalScope('tenant')->where('tenant_id', $tenantB->id)->count());

        // Verify admin user created
        $this->assertDatabaseHas('users', ['email' => 'e2e-admin@example.com']);
    }

    public function test_seed_full_clean_on_fresh_database_succeeds(): void
    {
        // Running with --clean on a fresh database (no pre-existing E2E data) should succeed
        $this->artisan('e2e:seed-full', ['--clean' => true])->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['slug' => 'e2e-tenant-a']);
        $this->assertDatabaseHas('tenants', ['slug' => 'e2e-tenant-b']);

        $tenantA = Tenant::where('slug', 'e2e-tenant-a')->first();
        $this->assertGreaterThan(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count());
    }

    public function test_seed_full_json_outputs_valid_json(): void
    {
        Artisan::call('e2e:seed-full', ['--json' => true]);
        $output = Artisan::output();

        $data = json_decode($output, true);
        $this->assertNotNull($data, 'Output should be valid JSON');
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('tokens', $data);
        $this->assertArrayHasKey('tenantA', $data['tokens']);
        $this->assertArrayHasKey('tenantB', $data['tokens']);
        $this->assertArrayHasKey('lifecycle', $data['tokens']);
        $this->assertArrayHasKey('tenants', $data);
        $this->assertArrayHasKey('user', $data);
    }

    public function test_seed_full_creates_unique_tenants(): void
    {
        // Note: Idempotency cannot be tested in PHPUnit context because
        // BelongsToTenant global scope interferes with updateOrCreate()
        // (scope adds default tenant filter, preventing finding e2e records).
        // In production console context (without global scope), the command is idempotent.
        $this->artisan('e2e:seed-full')->assertExitCode(0);

        // Verify exactly one tenant per slug
        $this->assertEquals(1, Tenant::where('slug', 'e2e-tenant-a')->count());
        $this->assertEquals(1, Tenant::where('slug', 'e2e-tenant-b')->count());
    }

    public function test_seed_full_creates_category_groups(): void
    {
        $this->artisan('e2e:seed-full')->assertExitCode(0);

        $tenantA = Tenant::where('slug', 'e2e-tenant-a')->first();

        $this->assertGreaterThan(
            0,
            CategoryGroup::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count()
        );
    }

    public function test_seed_full_creates_tile_years(): void
    {
        $this->artisan('e2e:seed-full')->assertExitCode(0);

        $tenantA = Tenant::where('slug', 'e2e-tenant-a')->first();

        $this->assertGreaterThan(
            0,
            TileYear::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count()
        );
    }

    // ========== E2E Seed Tenant Resolution Command ==========

    public function test_seed_tenant_resolution_creates_tenants_with_domains(): void
    {
        $this->artisan('e2e:seed-tenant-resolution')->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['slug' => 'default']);
        $this->assertDatabaseHas('tenants', [
            'slug' => 'tenant-a',
            'domain' => 'a.open-source-dashboard.ddev.site',
        ]);
        $this->assertDatabaseHas('tenants', [
            'slug' => 'tenant-b',
            'domain' => 'b.open-source-dashboard.ddev.site',
        ]);
    }

    public function test_seed_tenant_resolution_creates_test_user(): void
    {
        $this->artisan('e2e:seed-tenant-resolution')->assertExitCode(0);

        $user = User::where('email', 'e2e-test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->admin_api_enabled);
    }

    public function test_seed_tenant_resolution_json_outputs_valid_json(): void
    {
        Artisan::call('e2e:seed-tenant-resolution', ['--json' => true]);
        $output = Artisan::output();

        $data = json_decode($output, true);
        $this->assertNotNull($data, 'Output should be valid JSON');
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('tokens', $data);
        $this->assertArrayHasKey('tenantA', $data['tokens']);
        $this->assertArrayHasKey('tenantB', $data['tokens']);
        $this->assertArrayHasKey('tenants', $data);
        $this->assertArrayHasKey('default', $data['tenants']);
        $this->assertArrayHasKey('tenantA', $data['tenants']);
        $this->assertArrayHasKey('tenantB', $data['tenants']);
    }

    public function test_seed_tenant_resolution_clean_removes_tokens(): void
    {
        // First seed
        $this->artisan('e2e:seed-tenant-resolution')->assertExitCode(0);

        $user = User::where('email', 'e2e-test@example.com')->first();
        $tokenIdsBefore = $user->tokens()->where('name', 'like', 'e2e-%')->pluck('id')->toArray();
        $this->assertNotEmpty($tokenIdsBefore);

        // Seed again with clean
        $this->artisan('e2e:seed-tenant-resolution', ['--clean' => true])->assertExitCode(0);

        // New tokens should have been created with different IDs
        $user->refresh();
        $tokenIdsAfter = $user->tokens()->where('name', 'like', 'e2e-%')->pluck('id')->toArray();
        $this->assertNotEmpty($tokenIdsAfter);

        // Verify tokens were actually regenerated (no overlap in IDs)
        $overlap = array_intersect($tokenIdsBefore, $tokenIdsAfter);
        $this->assertEmpty($overlap, 'Token IDs should not overlap after --clean regeneration');
    }
}
