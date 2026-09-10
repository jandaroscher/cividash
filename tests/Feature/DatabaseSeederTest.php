<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_uses_configured_admin_password(): void
    {
        Config::set('dashboard.seed_admin_password', 'configured-password-123');

        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('configured-password-123', $user->password));
    }

    public function test_seeder_generates_random_password_without_config(): void
    {
        Config::set('dashboard.seed_admin_password', null);

        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertFalse(Hash::check('password', $user->password));
    }
}
