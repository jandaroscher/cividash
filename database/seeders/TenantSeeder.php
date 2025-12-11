<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantSeeder extends Seeder
{
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
                'theme_config' => null,
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


