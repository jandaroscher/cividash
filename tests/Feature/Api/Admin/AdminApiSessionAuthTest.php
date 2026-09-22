<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Regression tests for the admin API role-escalation fix: Sanctum's
 * TransientToken (used for session/cookie auth) answers `can()` with true
 * for every ability, so the admin.api middleware must not rely on it and
 * must require `is_admin` for session-authenticated users instead.
 */
class AdminApiSessionAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_authenticated_non_admin_editor_is_forbidden_from_updating_general_config(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($editor)
            ->patchJson('/api/admin/config/general', []);

        $response->assertStatus(403);
    }

    public function test_session_authenticated_non_admin_editor_is_forbidden_from_importing(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $file = UploadedFile::fake()->createWithContent('bundle.json', '{}');

        $response = $this->actingAs($editor)
            ->post('/api/admin/import', ['file' => $file, 'mode' => 'dry_run']);

        $response->assertStatus(403);
    }

    public function test_session_authenticated_admin_can_update_general_config(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Tenant::create(['name' => 'Admin Test', 'slug' => 'admin-test', 'domain' => 'admin-test.example.com']);

        $response = $this->actingAs($admin)
            ->patchJson('http://admin-test.example.com/api/admin/config/general', ['site_name' => 'Neuer Name']);

        $response->assertStatus(200);
    }

    public function test_pat_with_admin_api_ability_still_succeeds_for_non_admin_user(): void
    {
        $admin = User::factory()->create(['is_admin' => false]);
        Tenant::create(['name' => 'Admin Test', 'slug' => 'admin-test-2', 'domain' => 'admin-test-2.example.com']);

        $token = $admin->createToken('test-token', ['admin-api'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('http://admin-test-2.example.com/api/admin/config/general', ['site_name' => 'Token Name']);

        $response->assertStatus(200);
    }

    public function test_pat_without_admin_api_ability_still_returns_403(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('test-token', ['public-read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/admin/config/general', []);

        $response->assertStatus(403);
    }
}
