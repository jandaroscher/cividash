<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class RoleSeeder extends Seeder
{
    /**
     * Seed default roles for all tenants and set first user as admin.
     */
    public function run(): void
    {
        $roleService = app(RoleService::class);

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->command->info("Creating roles for tenant: {$tenant->name} ({$tenant->slug})");

            // Create default roles (Redakteur)
            $roleService->createDefaultRolesForTenant($tenant);

            // Set first user attached to this tenant as admin
            $users = $tenant->users()->orderBy('id')->get();
            if ($users->isNotEmpty()) {
                $firstUser = $users->first();
                if (! $firstUser->is_admin) {
                    $firstUser->forceFill(['is_admin' => true])->save();
                    $this->command->info("  -> Set user as admin: {$firstUser->email}");
                    Log::info('RoleSeeder: Set user as admin', [
                        'tenant_id' => $tenant->id,
                        'user_id' => $firstUser->id,
                    ]);
                }
            }
        }

        $this->command->info('Role seeding completed.');
    }
}
