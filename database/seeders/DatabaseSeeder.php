<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates a single test User with name "Test User" and email "test@example.com",
     * then runs the TenantSeeder to seed tenant-related data.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'admin_api_enabled' => app()->isLocal() || app()->environment('testing'),
        ]);

        $this->call(TenantSeeder::class);
    }
}
