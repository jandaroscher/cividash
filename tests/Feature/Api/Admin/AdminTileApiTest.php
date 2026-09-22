<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTileApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach([$this->tenant->id, $this->otherTenant->id]);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Create test data in a specific tenant context using Filament.
     * This is only for DATA SETUP, not for request authentication.
     */
    protected function createTileInTenant(Tenant $tenant, array $data): Tile
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);

        $tile = Tile::create($data);

        Filament::setTenant(null);
        Filament::auth()->logout();

        return $tile;
    }

    /**
     * Create a Sanctum token with tenant_id for API requests.
     * This is the realistic path: token-based tenant resolution.
     */
    protected function createTokenForTenant(Tenant $tenant, array $abilities = ['admin-api']): string
    {
        $token = $this->user->createToken('test-token', $abilities);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    /**
     * Create a Sanctum token WITHOUT tenant_id (should trigger 400).
     */
    protected function createTokenWithoutTenant(array $abilities = ['admin-api']): string
    {
        $token = $this->user->createToken('test-token-no-tenant', $abilities);

        // Explicitly NOT setting tenant_id
        return $token->plainTextToken;
    }

    // ========== Tenant Context Hardening Tests ==========

    public function test_admin_request_without_token_tenant_id_returns_400(): void
    {
        $token = $this->createTokenWithoutTenant();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Test', 'en' => 'Test'],
                'slug' => ['de' => 'test', 'en' => 'test'],
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'missing_tenant_context',
            ]);
    }

    // ========== POST Tests ==========

    public function test_can_create_tile_within_tenant_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Neue Kachel', 'en' => 'New Tile'],
                'slug' => ['de' => 'neue-kachel', 'en' => 'new-tile'],
                'description' => ['de' => 'Beschreibung', 'en' => 'Description'],
                'position' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'description']]);

        $this->assertDatabaseHas('tiles', [
            'tenant_id' => $this->tenant->id,
            'position' => 1,
        ]);
    }

    public function test_created_tile_gets_tenant_id_from_context_not_payload(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        // Attempt to pass a different tenant_id in payload (should be ignored)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Kachel', 'en' => 'Tile'],
                'slug' => ['de' => 'kachel', 'en' => 'tile'],
                'description' => ['de' => 'Desc', 'en' => 'Desc'],
                'tenant_id' => $this->otherTenant->id, // This should be ignored
            ]);

        $response->assertStatus(201);

        $tile = Tile::withoutGlobalScope('tenant')->latest('id')->first();
        $this->assertEquals($this->tenant->id, $tile->tenant_id);
        $this->assertNotEquals($this->otherTenant->id, $tile->tenant_id);
    }

    public function test_create_tile_validates_required_fields(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/admin/tiles', [
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'slug' => ['de' => 'test', 'en' => 'test'],
        ]);

        $response->assertStatus(401);
    }

    public function test_slug_is_autofilled_when_missing(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tiles', [
                'title' => ['de' => 'Neue Kachel', 'en' => 'New Tile'],
                'description' => ['de' => 'Beschreibung', 'en' => 'Description'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug.de', 'neue-kachel')
            ->assertJsonPath('data.slug.en', 'new-tile');
    }

    // ========== PATCH Tests ==========

    public function test_can_update_tile_within_tenant_context(): void
    {
        $tile = $this->createTileInTenant($this->tenant, [
            'title' => ['de' => 'Original', 'en' => 'Original'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tiles/{$tile->id}", [
                'title' => ['de' => 'Updated', 'en' => 'Updated'],
                'slug' => ['de' => 'updated', 'en' => 'updated'],
                'position' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.position', 5);

        $this->assertDatabaseHas('tiles', [
            'id' => $tile->id,
            'position' => 5,
        ]);
    }

    public function test_cannot_update_tile_from_different_tenant(): void
    {
        // Create tile in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tiles/{$otherTile->id}", [
                'title' => ['de' => 'Hacked', 'en' => 'Hacked'],
                'slug' => ['de' => 'hacked', 'en' => 'hacked'],
            ]);

        // Should return 404 because tile is not visible in token's tenant context
        $response->assertStatus(404);
    }

    public function test_cannot_change_tenant_id_via_patch(): void
    {
        $tile = $this->createTileInTenant($this->tenant, [
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tiles/{$tile->id}", [
                'title' => ['de' => 'Updated', 'en' => 'Updated'],
                'slug' => ['de' => 'updated', 'en' => 'updated'],
                'tenant_id' => $this->otherTenant->id, // Attempt to change tenant
            ]);

        $response->assertStatus(200);

        // Verify tenant_id was not changed
        $tile->refresh();
        $this->assertEquals($this->tenant->id, $tile->tenant_id);
    }

    public function test_patch_validates_fields(): void
    {
        $tile = $this->createTileInTenant($this->tenant, [
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tiles/{$tile->id}", [
                'position' => 'not-a-number', // Invalid
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    // ========== DELETE Tests ==========

    public function test_can_delete_tile_within_tenant_context(): void
    {
        $tile = $this->createTileInTenant($this->tenant, [
            'title' => ['de' => 'To Delete', 'en' => 'To Delete'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/tiles/{$tile->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tiles', [
            'id' => $tile->id,
        ]);
    }

    public function test_cannot_delete_tile_from_different_tenant(): void
    {
        // Create tile in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/tiles/{$otherTile->id}");

        // Should return 404 because tile is not visible in token's tenant context
        $response->assertStatus(404);

        // Verify tile still exists
        $this->assertDatabaseHas('tiles', [
            'id' => $otherTile->id,
        ]);
    }

    public function test_delete_nonexistent_tile_returns_404(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/admin/tiles/99999');

        $response->assertStatus(404);
    }
}
