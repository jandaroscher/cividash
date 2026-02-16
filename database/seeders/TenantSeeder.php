<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantSeeder extends Seeder
{
    /**
     * Seed tenants with domains and associate users.
     *
     * Creates or reuses demo/test users, configures the default tenant domain,
     * creates Stadt Regensburg and Demo City tenants with their respective domains,
     * attaches users to all tenants, and backfills tenancy associations.
     */
    public function run(): void
    {
        $demoUser = User::where('email', 'demo@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Demo Admin',
                'email' => 'demo@example.com',
            ]);

        $testUser = User::where('email', 'test@example.com')->first();

        // Derive base domain from APP_URL so the seeder works in any environment
        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        // Default tenant — set the main domain
        $defaultTenant = Tenant::where('slug', 'default')->first();
        if ($defaultTenant) {
            $defaultTenant->update(['domain' => $baseDomain]);
        }

        // Stadt Regensburg
        $regensburg = Tenant::firstOrCreate(
            ['slug' => 'stadt-regensburg'],
            ['name' => 'Stadt Regensburg']
        );
        $regensburg->update(['domain' => "regensburg.{$baseDomain}"]);
        $regensburg->users()->syncWithoutDetaching($demoUser->id);
        if ($testUser) {
            $regensburg->users()->syncWithoutDetaching($testUser->id);
        }

        // Demo City
        $demoCity = Tenant::firstOrCreate(
            ['slug' => 'demo-city'],
            ['name' => 'Demo City']
        );
        $demoCity->update(['domain' => "demo-city.{$baseDomain}"]);
        $demoCity->users()->syncWithoutDetaching($demoUser->id);
        if ($testUser) {
            $demoCity->users()->syncWithoutDetaching($testUser->id);
        }

        // Always set demo user's default tenant to Regensburg
        $demoUser->forceFill(['default_tenant_id' => $regensburg->id])->save();

        Artisan::call('tenancy:backfill');
    }
}
