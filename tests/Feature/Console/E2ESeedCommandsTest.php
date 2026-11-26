<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_seed_full_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('e2e:seed-full')->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'e2e-superadmin@example.com']);
    }

    public function test_seed_full_clean_removes_dashboards_registered_by_the_suite(): void
    {
        Tenant::create(['name' => 'E2E Neu', 'slug' => 'e2e-neu-123']);

        $this->artisan('e2e:seed-full', ['--clean' => true])->assertExitCode(0);

        $this->assertDatabaseMissing('tenants', ['slug' => 'e2e-neu-123']);
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

    public function test_seed_full_creates_time_periods(): void
    {
        $this->artisan('e2e:seed-full')->assertExitCode(0);

        $tenantA = Tenant::where('slug', 'e2e-tenant-a')->first();

        $this->assertGreaterThan(
            0,
            TimePeriod::withoutGlobalScope('tenant')->where('tenant_id', $tenantA->id)->count()
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

    public function test_seed_media_clean_puts_back_the_logo_and_favicon_settings_the_legacy_content_replaced(): void
    {
        Storage::fake('public');
        $this->artisan('e2e:seed-full')->assertSuccessful();
        $default = Tenant::where('slug', 'default')->firstOrFail();
        $setting = fn (int $tenant, string $group, string $name) => DB::table('settings')->where(['tenant_id' => $tenant, 'group' => $group, 'name' => $name]);
        $setting(0, 'branding', 'logo_url')->delete();
        $setting($default->id, 'general', 'favicon')->delete();
        DB::table('settings')->insert([
            ['tenant_id' => 0, 'group' => 'branding', 'name' => 'logo_url', 'payload' => json_encode('branding/city-logo.svg'), 'locked' => false],
            ['tenant_id' => $default->id, 'group' => 'branding', 'name' => 'logo_url', 'payload' => json_encode('branding/dashboard-logo.svg'), 'locked' => false],
        ]);

        $this->artisan('e2e:seed-media --legacy')->assertSuccessful();
        $this->artisan('e2e:seed-media --legacy')->assertSuccessful();
        $this->assertTrue($setting($default->id, 'general', 'favicon')->exists());
        $this->artisan('e2e:seed-media --clean')->assertSuccessful();

        $this->assertSame('branding/city-logo.svg', json_decode($setting(0, 'branding', 'logo_url')->value('payload')));
        $this->assertSame('branding/dashboard-logo.svg', json_decode($setting($default->id, 'branding', 'logo_url')->value('payload')));
        $this->assertFalse($setting($default->id, 'general', 'favicon')->exists());
        $this->assertFalse($setting(0, 'e2e', 'media_settings_backup')->exists(), 'The backup goes with the cleanup');
    }
}
