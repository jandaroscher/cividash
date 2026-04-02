<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $user;

    protected string $tokenA;

    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach([$this->tenantA->id, $this->tenantB->id]);

        $this->tokenA = $this->createTokenForTenant($this->tenantA);
        $this->tokenB = $this->createTokenForTenant($this->tenantB);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    protected function createTokenForTenant(Tenant $tenant, array $abilities = ['admin-api']): string
    {
        $token = $this->user->createToken('test-'.uniqid(), $abilities);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    protected function createInTenant(Tenant $tenant, callable $factory): mixed
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($tenant);

        $result = $factory();

        Filament::setTenant(null);

        return $result;
    }

    // ========== MetricDefinition Cross-Tenant ==========

    public function test_cannot_update_metric_definition_from_other_tenant(): void
    {
        $definition = $this->createInTenant($this->tenantB, function () {
            $tile = Tile::factory()->create();

            return MetricDefinition::factory()->forTile($tile)->create();
        });

        $response = $this->withToken($this->tokenA)
            ->patchJson("/api/admin/metric-definitions/{$definition->id}", ['label' => ['de' => 'Hacked']]);

        $response->assertNotFound();
    }

    public function test_cannot_delete_metric_definition_from_other_tenant(): void
    {
        $definition = $this->createInTenant($this->tenantB, function () {
            $tile = Tile::factory()->create();

            return MetricDefinition::factory()->forTile($tile)->create();
        });

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/admin/metric-definitions/{$definition->id}");

        $response->assertNotFound();
    }

    // ========== MetricValue Cross-Tenant ==========

    public function test_cannot_update_metric_value_from_other_tenant(): void
    {
        $value = $this->createInTenant($this->tenantB, function () {
            $tile = Tile::factory()->create();
            $definition = MetricDefinition::factory()->forTile($tile)->create();

            return MetricValue::factory()->forDefinition($definition)->create();
        });

        $response = $this->withToken($this->tokenA)
            ->patchJson("/api/admin/metric-values/{$value->id}", ['value' => 999]);

        $response->assertNotFound();
    }

    public function test_cannot_delete_metric_value_from_other_tenant(): void
    {
        $value = $this->createInTenant($this->tenantB, function () {
            $tile = Tile::factory()->create();
            $definition = MetricDefinition::factory()->forTile($tile)->create();

            return MetricValue::factory()->forDefinition($definition)->create();
        });

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/admin/metric-values/{$value->id}");

        $response->assertNotFound();
    }

    // ========== CategoryGroup Cross-Tenant ==========

    public function test_cannot_update_category_group_from_other_tenant(): void
    {
        $group = $this->createInTenant($this->tenantB, fn () => CategoryGroup::factory()->create());

        $response = $this->withToken($this->tokenA)
            ->patchJson("/api/admin/category-groups/{$group->id}", ['name' => 'Hacked']);

        $response->assertNotFound();
    }

    public function test_cannot_delete_category_group_from_other_tenant(): void
    {
        $group = $this->createInTenant($this->tenantB, fn () => CategoryGroup::factory()->create());

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/admin/category-groups/{$group->id}");

        $response->assertNotFound();
    }

    // ========== Category Cross-Tenant ==========

    public function test_cannot_update_category_from_other_tenant(): void
    {
        $category = $this->createInTenant($this->tenantB, function () {
            $group = CategoryGroup::factory()->create();

            return Category::factory()->create(['category_group_id' => $group->id]);
        });

        $response = $this->withToken($this->tokenA)
            ->patchJson("/api/admin/categories/{$category->id}", ['name' => 'Hacked']);

        $response->assertNotFound();
    }

    public function test_cannot_delete_category_from_other_tenant(): void
    {
        $category = $this->createInTenant($this->tenantB, function () {
            $group = CategoryGroup::factory()->create();

            return Category::factory()->create(['category_group_id' => $group->id]);
        });

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertNotFound();
    }

    // ========== TileYear Cross-Tenant ==========

    public function test_cannot_update_tile_year_from_other_tenant(): void
    {
        $tileYear = $this->createInTenant($this->tenantB, function () {
            $tile = Tile::factory()->create();

            return TileYear::factory()->create(['tile_id' => $tile->id]);
        });

        $response = $this->withToken($this->tokenA)
            ->patchJson("/api/admin/tile-years/{$tileYear->id}", ['year' => 2020]);

        $response->assertNotFound();
    }

    public function test_cannot_delete_tile_year_from_other_tenant(): void
    {
        $tileYear = $this->createInTenant($this->tenantB, function () {
            $tile = Tile::factory()->create();

            return TileYear::factory()->create(['tile_id' => $tile->id]);
        });

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/admin/tile-years/{$tileYear->id}");

        $response->assertNotFound();
    }

    // ========== Page Cross-Tenant ==========

    public function test_cannot_update_page_from_other_tenant(): void
    {
        $page = $this->createInTenant($this->tenantB, fn () => \App\Models\Page::factory()->create());

        $response = $this->withToken($this->tokenA)
            ->patchJson("/api/admin/pages/{$page->id}", ['title' => 'Hacked']);

        // Controller may return 404 (not found in tenant scope) or 422 (validation fails on scoped lookup)
        $this->assertTrue(in_array($response->status(), [404, 422]), "Expected 404 or 422, got {$response->status()}");
    }

    public function test_cannot_delete_page_from_other_tenant(): void
    {
        $page = $this->createInTenant($this->tenantB, fn () => \App\Models\Page::factory()->create());

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/admin/pages/{$page->id}");

        $response->assertNotFound();
    }

    // Positive same-tenant CRUD is covered in AdminTileApiTest
}
