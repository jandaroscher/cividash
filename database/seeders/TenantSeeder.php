<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantSeeder extends Seeder
{
    /**
     * Ensures a demo admin user and a specific tenant exist, associates them, and backfills tenancy associations.
     *
     * Creates or reuses a "Demo Admin" user (email demo@example.com) and a tenant with slug "stadt-regensburg" and name "Stadt Regensburg", attaches the user to the tenant without detaching other associations, sets the user's default_tenant_id to the tenant if it is null, and invokes the tenancy backfill command to assign existing records to a tenant.
     */
    public function run(): void
    {
        $user = User::where('email', 'demo@example.com')->first() ?? User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'demo@example.com',
        ]);

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'stadt-regensburg'],
            [
                'name' => 'Stadt Regensburg',
            ]
        );

        $tenant->users()->syncWithoutDetaching($user->id);

        if ($user->default_tenant_id === null) {
            $user->forceFill(['default_tenant_id' => $tenant->id])->save();
        }

        // Ensure existing records are assigned to a tenant after seeding.
        Artisan::call('tenancy:backfill');
    }
}
