<?php

namespace Tests\Feature\Api\Admin;

use App\Http\Requests\Admin\UpdateTimePeriodRequest;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTimePeriodApiTest extends TestCase
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
    protected function createTimePeriodInTenant(Tenant $tenant, Tile $tile, string $periodKey): TimePeriod
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);

        $timePeriod = TimePeriod::create([
            'tile_id' => $tile->id,
            'period_key' => $periodKey,
            'granularity' => 'year',
            'label' => $periodKey,
        ]);

        Filament::setTenant(null);

        return $timePeriod;
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
            ->postJson('/api/admin/time-periods', [
                'tile_id' => $this->tile->id,
                'period_key' => '2024',
                'granularity' => 'year',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'missing_tenant_context',
            ]);
    }

    // ========== POST Tests ==========

    public function test_can_create_time_period_within_tenant_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/time-periods', [
                'tile_id' => $this->tile->id,
                'period_key' => '2024',
                'granularity' => 'year',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'tile_id', 'period_key']]);

        $this->assertDatabaseHas('time_periods', [
            'tenant_id' => $this->tenant->id,
            'tile_id' => $this->tile->id,
            'period_key' => '2024',
        ]);
    }

    public function test_created_time_period_gets_tenant_id_from_context(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/time-periods', [
                'tile_id' => $this->tile->id,
                'period_key' => '2025',
                'granularity' => 'year',
                'tenant_id' => $this->otherTenant->id, // Should be ignored
            ]);

        $response->assertStatus(201);

        $timePeriod = TimePeriod::withoutGlobalScope('tenant')->latest('id')->first();
        $this->assertEquals($this->tenant->id, $timePeriod->tenant_id);
    }

    public function test_create_time_period_validates_required_fields(): void
    {
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/time-periods', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tile_id', 'period_key']);
    }

    // ========== PATCH Tests ==========

    public function test_can_update_time_period_within_tenant_context(): void
    {
        $timePeriod = $this->createTimePeriodInTenant($this->tenant, $this->tile, '2020');
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/time-periods/{$timePeriod->id}", [
                'period_key' => '2025',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.period_key', '2025')
            ->assertJsonPath('data.label', '2025');

        // Verify label was regenerated in DB
        $timePeriod->refresh();
        $this->assertEquals('2025', $timePeriod->label);
    }

    /**
     * Regression: rules() must not query the DB / throw when there is no
     * route id. Scribe's API-doc generator instantiates the FormRequest with
     * no bound route to extract body parameters, which previously triggered a
     * "no such table: time_periods" QueryException and failed the CI docs step.
     *
     * @see UpdateTimePeriodRequest::resolveTimePeriod()
     */
    public function test_update_rules_do_not_throw_without_route_id(): void
    {
        $request = new UpdateTimePeriodRequest;

        $rules = $request->rules();

        $this->assertArrayHasKey('period_key', $rules);
        // Without a resolvable record, the DB-dependent uniqueness rule is
        // skipped (empty string), leaving only the static constraints.
        $this->assertContains('', $rules['period_key']);
    }

    public function test_cannot_update_time_period_from_different_tenant(): void
    {
        // Create time period in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        $otherTimePeriod = $this->createTimePeriodInTenant($this->otherTenant, $otherTile, '2020');

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/time-periods/{$otherTimePeriod->id}", [
                'period_key' => '2025',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_change_tenant_id_via_patch_time_period(): void
    {
        $timePeriod = $this->createTimePeriodInTenant($this->tenant, $this->tile, '2020');
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/time-periods/{$timePeriod->id}", [
                'period_key' => '2021',
                'tenant_id' => $this->otherTenant->id,
            ]);

        $response->assertStatus(200);

        $timePeriod->refresh();
        $this->assertEquals($this->tenant->id, $timePeriod->tenant_id);
    }

    // ========== DELETE Tests ==========

    public function test_can_delete_time_period_within_tenant_context(): void
    {
        $timePeriod = $this->createTimePeriodInTenant($this->tenant, $this->tile, '2020');
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/time-periods/{$timePeriod->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('time_periods', [
            'id' => $timePeriod->id,
        ]);
    }

    public function test_cannot_delete_time_period_from_different_tenant(): void
    {
        // Create time period in other tenant
        $otherTile = $this->createTileInTenant($this->otherTenant, [
            'title' => ['de' => 'Other', 'en' => 'Other'],
            'description' => ['de' => 'Desc', 'en' => 'Desc'],
        ]);
        $otherTimePeriod = $this->createTimePeriodInTenant($this->otherTenant, $otherTile, '2020');

        // Request with token for our tenant (not otherTenant)
        $token = $this->createTokenForTenant($this->tenant);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/time-periods/{$otherTimePeriod->id}");

        $response->assertStatus(404);
    }

    // ========== Auth Tests ==========

    public function test_unauthenticated_post_returns_401(): void
    {
        // Ensure no residual auth from setUp
        Filament::auth()->logout();

        $response = $this->postJson('/api/admin/time-periods', [
            'tile_id' => 1,
            'period_key' => '2024',
            'granularity' => 'year',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        Filament::auth()->logout();

        $response = $this->patchJson('/api/admin/time-periods/1', [
            'period_key' => '2025',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        Filament::auth()->logout();

        $response = $this->deleteJson('/api/admin/time-periods/1');

        $response->assertStatus(401);
    }
}
