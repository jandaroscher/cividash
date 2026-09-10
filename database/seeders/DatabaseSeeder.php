<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $configuredPassword = config('dashboard.seed_admin_password');
        $password = $configuredPassword ?: Str::password(20);

        User::factory()->admin()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => $password,
        ]);

        if (! $configuredPassword) {
            $this->command?->info("Seeded admin test@example.com with password: {$password}");
        }

        $this->call(TenantSeeder::class);
        $this->call(RoleSeeder::class);
    }
}
