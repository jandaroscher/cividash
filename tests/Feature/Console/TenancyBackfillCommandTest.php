<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_default_tenant(): void
    {
        // Remove the default tenant created by base TestCase
        Tenant::where('slug', 'default')->delete();

        $this->artisan('tenancy:backfill')
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['slug' => 'default']);
    }

    public function test_custom_tenant_slug(): void
    {
        $this->artisan('tenancy:backfill', ['--default-tenant' => 'custom-slug'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['slug' => 'custom-slug']);
    }

    public function test_associates_users_with_tenant(): void
    {
        $user = User::factory()->create();

        $this->artisan('tenancy:backfill')
            ->assertExitCode(0);

        $tenant = Tenant::where('slug', 'default')->first();
        $this->assertTrue($user->tenants()->where('tenants.id', $tenant->id)->exists());
    }

    public function test_sets_user_default_tenant_when_null(): void
    {
        $user = User::factory()->create(['default_tenant_id' => null]);

        $this->artisan('tenancy:backfill')
            ->assertExitCode(0);

        $user->refresh();
        $tenant = Tenant::where('slug', 'default')->first();
        $this->assertEquals($tenant->id, $user->default_tenant_id);
    }

    public function test_does_not_overwrite_existing_default_tenant_id(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $user = User::factory()->create(['default_tenant_id' => $otherTenant->id]);

        $this->artisan('tenancy:backfill')
            ->assertExitCode(0);

        $user->refresh();
        // Should not be overwritten
        $this->assertEquals($otherTenant->id, $user->default_tenant_id);
    }

    public function test_does_not_overwrite_existing_tenant_id(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);

        $tile = Tile::withoutGlobalScope('tenant')->create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
            'slug' => ['de' => 'test', 'en' => 'test'],
            'tenant_id' => $otherTenant->id,
            'position' => 0,
        ]);

        $this->artisan('tenancy:backfill')
            ->assertExitCode(0);

        $tile->refresh();
        $this->assertEquals($otherTenant->id, $tile->tenant_id);
    }

    public function test_backfills_null_tenant_id_records(): void
    {
        // Create a tile with null tenant_id directly via DB
        $tileId = \Illuminate\Support\Facades\DB::table('tiles')->insertGetId([
            'title' => json_encode(['de' => 'Unassigned', 'en' => 'Unassigned']),
            'description' => json_encode(['de' => 'Desc', 'en' => 'Desc']),
            'slug' => json_encode(['de' => 'unassigned', 'en' => 'unassigned']),
            'tenant_id' => null,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('tenancy:backfill')
            ->assertExitCode(0);

        $tile = Tile::withoutGlobalScope('tenant')->find($tileId);
        $tenant = Tenant::where('slug', 'default')->first();
        $this->assertEquals($tenant->id, $tile->tenant_id);
    }

    public function test_backfill_is_idempotent(): void
    {
        User::factory()->create(['default_tenant_id' => null]);

        $this->artisan('tenancy:backfill')->assertExitCode(0);
        $this->artisan('tenancy:backfill')->assertExitCode(0);

        // Should only have one default tenant
        $this->assertEquals(1, Tenant::where('slug', 'default')->count());
    }

    public function test_backfill_with_custom_slug_creates_correct_tenant(): void
    {
        $user = User::factory()->create();
        // User::booted() auto-sets default_tenant_id to the default tenant,
        // so we need to clear it manually to simulate a user without a default tenant
        $user->forceFill(['default_tenant_id' => null])->save();

        $this->artisan('tenancy:backfill', ['--default-tenant' => 'my-org'])
            ->assertExitCode(0);

        $tenant = Tenant::where('slug', 'my-org')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('Default Tenant', $tenant->name);

        $user->refresh();
        $this->assertEquals($tenant->id, $user->default_tenant_id);
    }
}
