<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_active_admin_with_the_prompted_password(): void
    {
        $this->artisan('cividash:create-admin', [
            'email' => 'admin@example.org',
            '--first-name' => 'Ada',
            '--last-name' => 'Admin',
        ])
            ->expectsQuestion('Password', 'a-strong-password')
            ->expectsQuestion('Confirm password', 'a-strong-password')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.org')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertSame('Ada', $user->first_name);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
    }

    public function test_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'admin@example.org']);

        $this->artisan('cividash:create-admin', ['email' => 'admin@example.org'])
            ->assertFailed();
    }
}
