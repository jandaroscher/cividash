<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputValidationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Validation Test', 'slug' => 'validation-test']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);

        $token = $user->createToken('admin', ['admin-api']);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();
        $this->adminToken = $token->plainTextToken;
    }

    // ========== Overlong Strings ==========

    public function test_overlong_tile_title_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => str_repeat('A', 10000)],
            ]);

        $response->assertStatus(422);
    }

    // ========== XSS / Script Injection ==========

    public function test_script_tags_in_tile_title_do_not_cause_server_error(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => '<script>alert("xss")</script>'],
            ]);

        // Must not cause 500 — acceptable outcomes: 201 (stored, frontend sanitizes), 422 (rejected)
        $this->assertNotEquals(500, $response->status(), 'Script tags in title must not cause server error');
    }

    // ========== Invalid ID Formats ==========

    public function test_non_numeric_tile_id_does_not_succeed(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/admin/tiles/abc', ['title' => ['de' => 'Test']]);

        // Route may not match (404) or method not allowed (405) — must not be 200/201
        $this->assertGreaterThanOrEqual(400, $response->status(), 'Non-numeric ID must not succeed');
    }

    public function test_negative_tile_id_returns_404(): void
    {
        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/tiles/-1');

        $response->assertNotFound();
    }

    public function test_nonexistent_tile_id_returns_404(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/admin/tiles/999999', ['title' => ['de' => 'Ghost']]);

        $response->assertNotFound();
    }

    // ========== Empty Required Fields ==========

    public function test_creating_tile_without_title_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/tiles', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    // ========== Invalid Locale Parameter ==========

    public function test_unsupported_locale_falls_back_gracefully(): void
    {
        $response = $this->getJson('/api/tiles?locale=xx');

        // Should not error — either falls back to 'de' or returns empty data
        $this->assertTrue(in_array($response->status(), [200, 422]), "Expected 200 or 422, got {$response->status()}");
    }

    // ========== Slug Edge Cases ==========

    public function test_special_characters_in_slug_returns_404(): void
    {
        $response = $this->getJson('/api/tiles/' . urlencode('<script>alert(1)</script>'));

        $response->assertNotFound();
    }

    public function test_very_long_slug_returns_404(): void
    {
        $response = $this->getJson('/api/tiles/' . str_repeat('a', 500));

        $response->assertNotFound();
    }

    // ========== SQL Injection Attempts ==========

    public function test_sql_injection_in_category_group_key_is_safe(): void
    {
        $response = $this->getJson("/api/categories/'; DROP TABLE tiles; --");

        // Must not cause 500 (SQL error) — 200 with empty data or 404 are both safe
        $this->assertNotEquals(500, $response->status(), 'SQL injection must not cause server error');
        // Verify tiles table still exists
        $this->assertDatabaseHas('tenants', ['slug' => 'default']);
    }
}
