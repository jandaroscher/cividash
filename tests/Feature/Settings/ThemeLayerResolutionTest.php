<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\Theme;
use App\Settings\TenantAwareDatabaseSettingsRepository;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tests\TestCase;

class ThemeLayerResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        parent::tearDown();
    }

    private function setGlobal(string $name, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => $name, 'tenant_id' => TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID],
            ['payload' => json_encode($value)],
        );
    }

    private function setTenantOverride(Tenant $tenant, string $name, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => $name, 'tenant_id' => $tenant->id],
            ['payload' => json_encode($value)],
        );
    }

    private function resolveBranding(Tenant $tenant): array
    {
        Filament::setTenant($tenant, isQuiet: true);

        /** @var TenantAwareDatabaseSettingsRepository $repo */
        $repo = SettingsRepositoryFactory::create();

        return $repo->getPropertiesInGroup('branding');
    }

    public function test_tenant_with_theme_and_no_override_sees_theme_value(): void
    {
        $this->setGlobal('accent_color', '#111111');

        $theme = Theme::factory()->create([
            'settings' => ['branding' => ['accent_color' => '#222222']],
        ]);
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);

        $props = $this->resolveBranding($tenant);

        $this->assertSame('#222222', $props['accent_color']);
    }

    public function test_tenant_override_beats_theme_value(): void
    {
        $this->setGlobal('accent_color', '#111111');

        $theme = Theme::factory()->create([
            'settings' => ['branding' => ['accent_color' => '#222222']],
        ]);
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);
        $this->setTenantOverride($tenant, 'accent_color', '#333333');

        $props = $this->resolveBranding($tenant);

        $this->assertSame('#333333', $props['accent_color']);
    }

    public function test_theme_value_beats_global_default(): void
    {
        $this->setGlobal('primary_color', '#aaaaaa');

        $theme = Theme::factory()->create([
            'settings' => ['branding' => ['primary_color' => '#bbbbbb']],
        ]);
        $tenant = Tenant::factory()->create(['theme_id' => $theme->id]);

        $props = $this->resolveBranding($tenant);

        $this->assertSame('#bbbbbb', $props['primary_color']);
        $this->assertNotSame('#aaaaaa', $props['primary_color']);
    }

    public function test_tenant_without_theme_is_unaffected_by_theme_layer(): void
    {
        $this->setGlobal('primary_color', '#aaaaaa');

        $tenant = Tenant::factory()->create(['theme_id' => null]);
        $this->setTenantOverride($tenant, 'primary_color', '#ccccccc');

        $props = $this->resolveBranding($tenant);

        // Unchanged today's behaviour: tenant override over global, theme step is a no-op.
        $this->assertSame('#ccccccc', $props['primary_color']);
    }

    public function test_two_tenants_sharing_one_theme_both_see_theme_values(): void
    {
        $this->setGlobal('accent_color', '#111111');

        $theme = Theme::factory()->create([
            'settings' => ['branding' => ['accent_color' => '#222222']],
        ]);
        $tenantA = Tenant::factory()->create(['theme_id' => $theme->id]);
        $tenantB = Tenant::factory()->create(['theme_id' => $theme->id]);

        $this->assertSame('#222222', $this->resolveBranding($tenantA)['accent_color']);
        $this->assertSame('#222222', $this->resolveBranding($tenantB)['accent_color']);
    }
}
