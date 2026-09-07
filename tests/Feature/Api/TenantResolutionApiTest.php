<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Theme;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolutionApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Tenant $defaultTenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Get or create default tenant (TestCase may have already created it)
        $this->defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default']
        );
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'domain' => 'tenant-a.example.com',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'domain' => 'tenant-b.example.com',
        ]);

        $this->user = User::factory()->create();
        $this->user->tenants()->syncWithoutDetaching([
            $this->tenantA->id,
            $this->tenantB->id,
        ]);

        // Create tiles in different tenants
        Filament::auth()->login($this->user);

        Filament::setTenant($this->tenantA);
        Tile::create([
            'title' => ['de' => 'Tile A', 'en' => 'Tile A'],
            'slug' => ['de' => 'tile-a', 'en' => 'tile-a'],
            'description' => ['de' => 'Tenant A', 'en' => 'Tenant A'],
        ]);

        Filament::setTenant($this->tenantB);
        Tile::create([
            'title' => ['de' => 'Tile B', 'en' => 'Tile B'],
            'slug' => ['de' => 'tile-b', 'en' => 'tile-b'],
            'description' => ['de' => 'Tenant B', 'en' => 'Tenant B'],
        ]);

        Filament::setTenant($this->defaultTenant);
        Tile::create([
            'title' => ['de' => 'Tile Default', 'en' => 'Tile Default'],
            'slug' => ['de' => 'tile-default', 'en' => 'tile-default'],
            'description' => ['de' => 'Default', 'en' => 'Default'],
        ]);

        Filament::setTenant(null);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    // ========== Token Resolution Tests ==========

    public function test_token_with_tenant_resolves_correct_data(): void
    {
        // Create token for tenant A
        $token = $this->user->createToken('test', ['public-read']);
        $token->accessToken->tenant_id = $this->tenantA->id;
        $token->accessToken->save();

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/tiles');

        $response->assertStatus(200);

        // Should only contain tenant A tiles
        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile A', $tiles[0]['title']['de']);
    }

    public function test_token_tenant_takes_priority_over_domain(): void
    {
        // Create token for tenant A
        $token = $this->user->createToken('test', ['public-read']);
        $token->accessToken->tenant_id = $this->tenantA->id;
        $token->accessToken->save();

        // Request with tenant A token but tenant B domain
        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->withHeader('Host', 'tenant-b.example.com')
            ->getJson('/api/tiles');

        $response->assertStatus(200);

        // Token should win - only tenant A tiles
        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile A', $tiles[0]['title']['de']);
    }

    // ========== Domain Resolution Tests ==========
    // Note: Full URLs are used because Laravel's test framework converts relative
    // URIs to http://localhost/... which overrides any HTTP_HOST server variable.
    // Using full URLs ensures $request->getHost() returns the correct domain.

    public function test_domain_resolves_correct_tenant(): void
    {
        $response = $this->getJson('http://tenant-a.example.com/api/tiles');

        $response->assertStatus(200);

        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile A', $tiles[0]['title']['de']);
    }

    public function test_www_prefix_is_stripped_from_domain(): void
    {
        $response = $this->getJson('http://www.tenant-b.example.com/api/tiles');

        $response->assertStatus(200);

        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile B', $tiles[0]['title']['de']);
    }

    // ========== Default Fallback Tests ==========

    public function test_no_token_no_domain_falls_back_to_default(): void
    {
        $response = $this->call('GET', '/api/tiles', [], [], [], [
            'HTTP_HOST' => 'unknown.example.com',
        ]);

        $response->assertStatus(200);

        // Should only contain default tenant tiles
        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile Default', $tiles[0]['title']['de']);
    }

    public function test_tiles_index_filters_inactive_tiles(): void
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($this->defaultTenant);

        Tile::create([
            'title' => ['de' => 'Inactive Tile', 'en' => 'Inactive Tile'],
            'slug' => ['de' => 'inactive-tile', 'en' => 'inactive-tile'],
            'is_public' => false,
        ]);

        Filament::setTenant(null);

        $response = $this->call('GET', '/api/tiles', [], [], [], [
            'HTTP_HOST' => 'unknown.example.com',
        ]);

        $response->assertStatus(200);

        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile Default', $tiles[0]['title']['de']);
    }

    // ========== Config Tenant Endpoint Tests ==========

    public function test_config_tenant_returns_resolved_tenant_info(): void
    {
        $token = $this->user->createToken('test', ['public-read']);
        $token->accessToken->tenant_id = $this->tenantA->id;
        $token->accessToken->save();

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/config/tenant');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'tenant-a',
                    'name' => 'Tenant A',
                    'domain' => 'tenant-a.example.com',
                    'resolved_by' => 'token',
                    'theme_slug' => null,
                ],
            ]);
    }

    public function test_config_tenant_returns_domain_resolution_info(): void
    {
        $response = $this->getJson('http://tenant-a.example.com/api/config/tenant');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'tenant-a',
                    'name' => 'Tenant A',
                    'domain' => 'tenant-a.example.com',
                    'resolved_by' => 'domain',
                    'theme_slug' => null,
                ],
            ]);
    }

    public function test_config_tenant_returns_default_resolution_info(): void
    {
        $response = $this->call('GET', '/api/config/tenant', [], [], [], [
            'HTTP_HOST' => 'unknown.example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'default',
                    'resolved_by' => 'default',
                    'theme_slug' => null,
                ],
            ]);
    }

    public function test_config_tenant_exposes_assigned_theme_slug(): void
    {
        $theme = Theme::create(['name' => 'Demo City', 'slug' => 'demo-city']);
        $this->tenantA->theme_id = $theme->id;
        $this->tenantA->save();

        $response = $this->getJson('http://tenant-a.example.com/api/config/tenant');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'tenant-a',
                    'theme_slug' => 'demo-city',
                ],
            ]);
    }

    // ========== Token Without Tenant Falls Back ==========

    public function test_token_without_tenant_id_falls_back_to_default(): void
    {
        // Create token WITHOUT tenant_id
        $token = $this->user->createToken('test', ['public-read']);
        // Don't set tenant_id

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/tiles');

        $response->assertStatus(200);

        // Without tenant_id on token and no domain match, falls back to default
        $tiles = $response->json('data');
        $this->assertCount(1, $tiles);
        $this->assertEquals('Tile Default', $tiles[0]['title']['de']);
    }
}
