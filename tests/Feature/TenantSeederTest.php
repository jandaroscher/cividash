<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_demo_user_and_tenant(): void
    {
        $this->seed(\Database\Seeders\TenantSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'demo@example.com',
            'first_name' => 'Demo',
            'last_name' => 'Admin',
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'stadt-regensburg',
            'name' => 'Stadt Regensburg',
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'demo-city',
            'name' => 'Demo City',
        ]);

        // Verify the user is associated with the tenants
        $user = User::where('email', 'demo@example.com')->first();
        $regensburg = Tenant::where('slug', 'stadt-regensburg')->first();
        $demoCity = Tenant::where('slug', 'demo-city')->first();

        $this->assertTrue(
            $user->tenants->contains($regensburg),
            'Demo user should be attached to the stadt-regensburg tenant'
        );
        $this->assertTrue(
            $user->tenants->contains($demoCity),
            'Demo user should be attached to the demo-city tenant'
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        // Run twice - should not crash or create duplicates
        $this->seed(\Database\Seeders\TenantSeeder::class);
        $this->seed(\Database\Seeders\TenantSeeder::class);

        $this->assertEquals(1, User::where('email', 'demo@example.com')->count());
        $this->assertEquals(1, Tenant::where('slug', 'stadt-regensburg')->count());
        $this->assertEquals(1, Tenant::where('slug', 'demo-city')->count());
    }

    public function test_seeder_preserves_existing_user_tenant_associations(): void
    {
        // Pre-create the user (User::booted() auto-attaches to default tenant)
        $user = User::factory()->create([
            'first_name' => 'Demo',
            'last_name' => 'Admin',
            'email' => 'demo@example.com',
        ]);

        // Run the seeder
        $this->seed(\Database\Seeders\TenantSeeder::class);

        // User should now belong to default, stadt-regensburg, and demo-city tenants
        $user->refresh();
        $this->assertEquals(3, $user->tenants()->count());

        $tenantSlugs = $user->tenants->pluck('slug')->sort()->values()->toArray();
        $this->assertEquals(['default', 'demo-city', 'stadt-regensburg'], $tenantSlugs);
    }
}
