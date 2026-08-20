<?php

namespace Tests\Feature\Theme;

use App\Exceptions\ThemeInUseException;
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

    public function test_deleting_theme_still_assigned_to_a_tenant_is_rejected(): void
    {
        // Product rule: an in-use theme cannot be deleted. The DB's
        // nullOnDelete on tenants.theme_id remains only as a safety net for
        // deletes that bypass Eloquent (see ThemeDeleteGuardTest).
        $theme = Theme::factory()->create();
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);

        $this->expectException(ThemeInUseException::class);

        $theme->delete();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'theme_id' => $theme->id]);
    }
}
