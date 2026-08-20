<?php

namespace Tests\Feature\Theme;

use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantThemeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_assigned_a_theme(): void
    {
        $theme = Theme::factory()->create(['slug' => 'ocean-blue']);
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);

        $tenant->refresh();

        $this->assertSame('ocean-blue', $tenant->theme->slug);
    }

    public function test_tenant_without_theme_has_null_theme(): void
    {
        $tenant = Tenant::factory()->create(['theme_id' => null]);

        $this->assertNull($tenant->theme);
    }

    public function test_theme_returns_its_assigned_tenants(): void
    {
        $theme = Theme::factory()->create();
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);
        Tenant::factory()->create(['theme_id' => null]);

        $theme->refresh();

        $this->assertCount(1, $theme->tenants);
        $this->assertTrue($theme->tenants->contains($tenant));
    }

    public function test_deleting_theme_nulls_theme_id_on_tenants_without_deleting_them(): void
    {
        $theme = Theme::factory()->create();
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);

        $theme->delete();
        $tenant->refresh();

        $this->assertNull($tenant->theme_id);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }
}
