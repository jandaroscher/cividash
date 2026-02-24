<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTileYearApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $user;

    protected Tile $tile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach([$this->tenant->id, $this->otherTenant->id]);

        // Create tile for test data using Filament context
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
        $this->tile = Tile::create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        Filament::setTenant(null);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Create a Sanctum token with tenant_id for API requests.
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

        return $token->plainTextToken;
    }

    /**
     * Create test data in a specific tenant context.
     */
    protected function createTileYearInTenant(Tenant $tenant, Tile $tile, int $year): TileYear
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);

        $tileYear = TileYear::create([
            'tile_id' => $tile->id,
            'year' => $year,
        ]);

        Filament::setTenant(null);

        return $tileYear;
    }

    /**
     * Create a tile in a specific tenant context.
     */
    protected function createTileInTenant(Tenant $tenant, array $data): Tile
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);

        $tile = Tile::create($data);

        Filament::setTenant(null);

        return $tile;
    }

    // ========== Tenant Context Hardening Tests ==========

    public function test_admin_request_without_token_tenant_id_returns_400(): void
    {
        $token = $this->createTokenWithoutTenant();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tile-years', [
                'tile_id' => $this->tile->id,
                'year' => 2024,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'missing_tenant_context',
            ]);
    }

    // ========== POST Tests ==========

    public function test_can_create_tile_year_within_tenant_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tile-years', [
                'tile_id' => $this->tile->id,
                'year' => 2024,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'tile_id', 'year']]);

        $this->assertDatabaseHas('tile_years', [
            'tenant_id' => $this->tenant->id,
            'tile_id' => $this->tile->id,
            'year' => 2024,
        ]);
    }

    public function test_created_tile_year_gets_tenant_id_from_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tile-years', [
                'tile_id' => $this->tile->id,
                'year' => 2025,
                'tenant_id' => $this->otherTenant->id, // Should be ignored
            ]);

        $response->assertStatus(201);

        $tileYear = TileYear::withoutGlobalScope('tenant')->latest('id')->first();
        $this->assertEquals($this->tenant->id, $tileYear->tenant_id);
    }

    public function test_create_tile_year_validates_required_fields(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/tile-years', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tile_id', 'year']);
    }

    // ========== PATCH Tests ==========

    public function test_can_update_tile_year_within_tenant_context(): void
    {
        $tileYear = $this->createTileYearInTenant($this->tenant, $this->tile, 2020);
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tile-years/{$tileYear->id}", [
                'year' => 2025,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.year', 2025);
    }

    public function test_cannot_update_tile_year_from_different_tenant(): void
    {
        // Create tile year in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        $otherTileYear = $this->createTileYearInTenant($this->otherTenant, $otherTile, 2020);

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tile-years/{$otherTileYear->id}", [
                'year' => 2025,
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_change_tenant_id_via_patch_tile_year(): void
    {
        $tileYear = $this->createTileYearInTenant($this->tenant, $this->tile, 2020);
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/tile-years/{$tileYear->id}", [
                'year' => 2021,
                'tenant_id' => $this->otherTenant->id,
            ]);

        $response->assertStatus(200);

        $tileYear->refresh();
        $this->assertEquals($this->tenant->id, $tileYear->tenant_id);
    }

    // ========== DELETE Tests ==========

    public function test_can_delete_tile_year_within_tenant_context(): void
    {
        $tileYear = $this->createTileYearInTenant($this->tenant, $this->tile, 2020);
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/tile-years/{$tileYear->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tile_years', [
            'id' => $tileYear->id,
        ]);
    }

    public function test_cannot_delete_tile_year_from_different_tenant(): void
    {
        // Create tile year in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        $otherTileYear = $this->createTileYearInTenant($this->otherTenant, $otherTile, 2020);

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/tile-years/{$otherTileYear->id}");

        $response->assertStatus(404);
    }

    // ========== Auth Tests ==========

    public function test_unauthenticated_post_returns_401(): void
    {
        // Ensure no residual auth from setUp
        Filament::auth()->logout();

        $response = $this->postJson('/api/admin/tile-years', [
            'tile_id' => 1,
            'year' => 2024,
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        Filament::auth()->logout();

        $response = $this->patchJson('/api/admin/tile-years/1', [
            'year' => 2025,
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        Filament::auth()->logout();

        $response = $this->deleteJson('/api/admin/tile-years/1');

        $response->assertStatus(401);
    }
}
