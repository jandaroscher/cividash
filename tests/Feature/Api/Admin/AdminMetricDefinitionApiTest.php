<?php

namespace Tests\Feature\Api\Admin;

use App\Models\MetricDefinition;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMetricDefinitionApiTest extends TestCase
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
        
        $this->user = User::factory()->create(['admin_api_enabled' => true]);
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

    /**
     * Create a metric definition in a specific tenant context.
     */
    protected function createMetricDefinitionInTenant(Tenant $tenant, Tile $tile, array $data): MetricDefinition
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);
        
        $definition = MetricDefinition::create(array_merge(['tile_id' => $tile->id], $data));
        
        Filament::setTenant(null);
        
        return $definition;
    }

    // ========== Tenant Context Hardening Tests ==========

    public function test_admin_request_without_token_tenant_id_returns_400(): void
    {
        $token = $this->createTokenWithoutTenant();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-definitions', [
                'tile_id' => $this->tile->id,
                'metric_key' => 'co2_emissions',
                'label' => ['de' => 'CO2 Emissionen', 'en' => 'CO2 Emissions'],
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'missing_tenant_context',
            ]);
    }

    // ========== POST Tests ==========

    public function test_can_create_metric_definition_within_tenant_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-definitions', [
                'tile_id' => $this->tile->id,
                'metric_key' => 'co2_emissions',
                'label' => ['de' => 'CO2 Emissionen', 'en' => 'CO2 Emissions'],
                'unit' => ['de' => 't', 'en' => 't'],
                'indicator_type' => 'value',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'tile_id', 'metric_key', 'label', 'unit']]);

        $this->assertDatabaseHas('metric_definitions', [
            'tenant_id' => $this->tenant->id,
            'metric_key' => 'co2_emissions',
        ]);
    }

    public function test_created_metric_definition_gets_tenant_id_from_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-definitions', [
                'tile_id' => $this->tile->id,
                'metric_key' => 'energy_use',
                'label' => ['de' => 'Energieverbrauch', 'en' => 'Energy Usage'],
                'unit' => ['de' => 'kWh', 'en' => 'kWh'],
                'tenant_id' => $this->otherTenant->id, // Should be ignored
            ]);

        $response->assertStatus(201);

        $definition = MetricDefinition::withoutGlobalScope('tenant')->latest('id')->first();
        $this->assertEquals($this->tenant->id, $definition->tenant_id);
    }

    public function test_create_metric_definition_validates_required_fields(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-definitions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tile_id', 'metric_key', 'label']);
    }

    // ========== PATCH Tests ==========

    public function test_can_update_metric_definition_within_tenant_context(): void
    {
        $definition = $this->createMetricDefinitionInTenant($this->tenant, $this->tile, [
            'metric_key' => 'old_key',
            'label' => ['de' => 'Old', 'en' => 'Old'],
            'unit' => ['de' => '%', 'en' => '%'],
        ]);
        
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/metric-definitions/{$definition->id}", [
                'metric_key' => 'new_key',
                'label' => ['de' => 'New Label', 'en' => 'New Label'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.metric_key', 'new_key');
    }

    public function test_cannot_update_metric_definition_from_different_tenant(): void
    {
        // Create definition in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        $otherDefinition = $this->createMetricDefinitionInTenant($this->otherTenant, $otherTile, [
            'metric_key' => 'other',
            'label' => ['de' => 'Other', 'en' => 'Other'],
        ]);

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/metric-definitions/{$otherDefinition->id}", [
                'metric_key' => 'hacked',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_change_tenant_id_via_patch_metric_definition(): void
    {
        $definition = $this->createMetricDefinitionInTenant($this->tenant, $this->tile, [
            'metric_key' => 'test',
            'label' => ['de' => 'Test', 'en' => 'Test'],
        ]);
        
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/metric-definitions/{$definition->id}", [
                'label' => ['de' => 'Updated', 'en' => 'Updated'],
                'tenant_id' => $this->otherTenant->id,
            ]);

        $response->assertStatus(200);
        
        $definition->refresh();
        $this->assertEquals($this->tenant->id, $definition->tenant_id);
    }

    // ========== DELETE Tests ==========

    public function test_can_delete_metric_definition_within_tenant_context(): void
    {
        $definition = $this->createMetricDefinitionInTenant($this->tenant, $this->tile, [
            'metric_key' => 'to_delete',
            'label' => ['de' => 'Delete', 'en' => 'Delete'],
        ]);
        
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/metric-definitions/{$definition->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('metric_definitions', [
            'id' => $definition->id,
        ]);
    }

    public function test_cannot_delete_metric_definition_from_different_tenant(): void
    {
        // Create definition in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        $otherDefinition = $this->createMetricDefinitionInTenant($this->otherTenant, $otherTile, [
            'metric_key' => 'other',
            'label' => ['de' => 'Other', 'en' => 'Other'],
        ]);

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/metric-definitions/{$otherDefinition->id}");

        $response->assertStatus(404);
    }

    // ========== Auth Tests ==========

    public function test_unauthenticated_post_returns_401(): void
    {
        // Ensure no residual auth from setUp
        Filament::auth()->logout();
        
        $response = $this->postJson('/api/admin/metric-definitions', [
            'tile_id' => 1,
            'metric_key' => 'test',
            'label' => ['de' => 'Test', 'en' => 'Test'],
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        Filament::auth()->logout();
        
        $response = $this->patchJson('/api/admin/metric-definitions/1', [
            'label' => ['de' => 'Updated', 'en' => 'Updated'],
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        Filament::auth()->logout();
        
        $response = $this->deleteJson('/api/admin/metric-definitions/1');

        $response->assertStatus(401);
    }
}
