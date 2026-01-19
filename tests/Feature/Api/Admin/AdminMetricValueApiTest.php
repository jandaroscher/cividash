<?php

namespace Tests\Feature\Api\Admin;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMetricValueApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $user;
    protected Tile $tile;
    protected TileYear $tileYear;
    protected MetricDefinition $metricDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
        
        $this->user = User::factory()->create(['admin_api_enabled' => true]);
        $this->user->tenants()->attach([$this->tenant->id, $this->otherTenant->id]);

        // Create test data using Filament context
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
        
        $this->tile = Tile::create([
            'title' => ['de' => 'Test', 'en' => 'Test'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        
        $this->tileYear = TileYear::create([
            'tile_id' => $this->tile->id,
            'year' => 2024,
        ]);
        
        $this->metricDefinition = MetricDefinition::create([
            'tile_id' => $this->tile->id,
            'metric_key' => 'test_metric',
            'label' => ['de' => 'Test', 'en' => 'Test'],
            'unit' => ['de' => '%', 'en' => '%'],
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
    protected function createTestDataInTenant(Tenant $tenant): array
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);
        
        $tile = Tile::create([
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        
        $tileYear = TileYear::create([
            'tile_id' => $tile->id,
            'year' => 2020,
        ]);
        
        $definition = MetricDefinition::create([
            'tile_id' => $tile->id,
            'metric_key' => 'other',
            'label' => ['de' => 'Other', 'en' => 'Other'],
        ]);
        
        Filament::setTenant(null);
        
        return compact('tile', 'tileYear', 'definition');
    }

    /**
     * Create a metric value in a specific tenant context.
     */
    protected function createMetricValueInTenant(Tenant $tenant, MetricDefinition $definition, TileYear $tileYear, float $value): MetricValue
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);
        
        $metricValue = MetricValue::create([
            'metric_definition_id' => $definition->id,
            'tile_year_id' => $tileYear->id,
            'value' => $value,
        ]);
        
        Filament::setTenant(null);
        
        return $metricValue;
    }

    // ========== Tenant Context Hardening Tests ==========

    public function test_admin_request_without_token_tenant_id_returns_400(): void
    {
        $token = $this->createTokenWithoutTenant();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-values', [
                'metric_definition_id' => $this->metricDefinition->id,
                'tile_year_id' => $this->tileYear->id,
                'value' => 42.5,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'missing_tenant_context',
            ]);
    }

    // ========== POST Tests ==========

    public function test_can_create_metric_value_within_tenant_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-values', [
                'metric_definition_id' => $this->metricDefinition->id,
                'tile_year_id' => $this->tileYear->id,
                'value' => 42.5,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'metric_definition_id', 'tile_year_id', 'value']]);

        $this->assertDatabaseHas('metric_values', [
            'tenant_id' => $this->tenant->id,
            'metric_definition_id' => $this->metricDefinition->id,
            'value' => 42.5,
        ]);
    }

    public function test_created_metric_value_gets_tenant_id_from_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-values', [
                'metric_definition_id' => $this->metricDefinition->id,
                'tile_year_id' => $this->tileYear->id,
                'value' => 100.0,
                'tenant_id' => $this->otherTenant->id, // Should be ignored
            ]);

        $response->assertStatus(201);

        $metricValue = MetricValue::withoutGlobalScope('tenant')->latest('id')->first();
        $this->assertEquals($this->tenant->id, $metricValue->tenant_id);
    }

    public function test_create_metric_value_validates_required_fields(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/metric-values', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['metric_definition_id', 'tile_year_id', 'value']);
    }

    // ========== PATCH Tests ==========

    public function test_can_update_metric_value_within_tenant_context(): void
    {
        $metricValue = $this->createMetricValueInTenant(
            $this->tenant,
            $this->metricDefinition,
            $this->tileYear,
            50.0
        );
        
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/metric-values/{$metricValue->id}", [
                'value' => 75.5,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', '75.50');
    }

    public function test_cannot_update_metric_value_from_different_tenant(): void
    {
        // Create metric value in other tenant
        $otherData = $this->createTestDataInTenant($this->otherTenant);
        $otherValue = $this->createMetricValueInTenant(
            $this->otherTenant,
            $otherData['definition'],
            $otherData['tileYear'],
            100
        );

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/metric-values/{$otherValue->id}", [
                'value' => 999,
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_change_tenant_id_via_patch_metric_value(): void
    {
        $metricValue = $this->createMetricValueInTenant(
            $this->tenant,
            $this->metricDefinition,
            $this->tileYear,
            50.0
        );
        
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/metric-values/{$metricValue->id}", [
                'value' => 60.0,
                'tenant_id' => $this->otherTenant->id,
            ]);

        $response->assertStatus(200);
        
        $metricValue->refresh();
        $this->assertEquals($this->tenant->id, $metricValue->tenant_id);
    }

    // ========== DELETE Tests ==========

    public function test_can_delete_metric_value_within_tenant_context(): void
    {
        $metricValue = $this->createMetricValueInTenant(
            $this->tenant,
            $this->metricDefinition,
            $this->tileYear,
            50.0
        );
        
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/metric-values/{$metricValue->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('metric_values', [
            'id' => $metricValue->id,
        ]);
    }

    public function test_cannot_delete_metric_value_from_different_tenant(): void
    {
        // Create metric value in other tenant
        $otherData = $this->createTestDataInTenant($this->otherTenant);
        $otherValue = $this->createMetricValueInTenant(
            $this->otherTenant,
            $otherData['definition'],
            $otherData['tileYear'],
            100
        );

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/metric-values/{$otherValue->id}");

        $response->assertStatus(404);
    }

    // ========== Auth Tests ==========

    public function test_unauthenticated_post_returns_401(): void
    {
        // Ensure no residual auth from setUp
        Filament::auth()->logout();
        
        $response = $this->postJson('/api/admin/metric-values', [
            'metric_definition_id' => 1,
            'tile_year_id' => 1,
            'value' => 42,
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        Filament::auth()->logout();
        
        $response = $this->patchJson('/api/admin/metric-values/1', [
            'value' => 50,
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        Filament::auth()->logout();
        
        $response = $this->deleteJson('/api/admin/metric-values/1');

        $response->assertStatus(401);
    }
}
