<?php

namespace Tests\Feature\Theme;

use App\Exceptions\ThemeInUseException;
use App\Models\Tenant;
use App\Models\Theme;
use App\Settings\BrandingSettings;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
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

        try {
            $theme->delete();
            $this->fail('Expected ThemeInUseException was not thrown.');
        } catch (ThemeInUseException) {
            // expected
        }

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'theme_id' => $theme->id]);
    }

    public function test_demo_city_seed_sets_every_branding_color_token(): void
    {
        // Non-color/non-palette fields BrandingSettings also defines (logo upload,
        // typography, tile group selectors) - the Demo City seed intentionally leaves
        // these to the global defaults, only the color palette must be complete.
        $nonColorFields = [
            'logo_url',
            'typography_font_family',
            'typography_font_weights',
            'typography_font_sizes',
            'typography_custom_font_name',
            'typography_custom_font_file',
            'tile_color_source_group_id',
            'tile_background_category_group_id',
        ];

        $allFields = array_map(
            fn ($property) => $property->getName(),
            array_filter(
                (new ReflectionClass(BrandingSettings::class))->getProperties(),
                fn ($property) => $property->isPublic() && ! $property->isStatic()
            )
        );

        $colorFields = array_diff($allFields, $nonColorFields);

        $demoCityTokens = TenantSeeder::demoCityBrandingTokens();

        foreach ($colorFields as $field) {
            $this->assertArrayHasKey($field, $demoCityTokens, "Demo City theme seed is missing branding token '{$field}'.");
        }

        $this->assertSame(['rail', 'handle', 'handleBorder'], array_keys($demoCityTokens['slider_colors']));
    }
}
