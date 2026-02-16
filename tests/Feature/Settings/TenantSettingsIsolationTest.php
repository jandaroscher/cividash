<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use App\Settings\TenantAwareDatabaseSettingsRepository;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSettingsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        parent::tearDown();
    }

    private function switchTenant(?Tenant $tenant): void
    {
        Filament::setTenant($tenant, isQuiet: true);
        $this->forgetSettings();
    }

    private function forgetSettings(): void
    {
        app()->forgetInstance(BrandingSettings::class);
        app()->forgetInstance(GeneralSettings::class);
    }

    public function test_tenant_a_writes_do_not_affect_tenant_b(): void
    {
        // Read original global default
        $globalDefault = app(BrandingSettings::class)->primary_color;
        $this->forgetSettings();

        // Tenant A writes a custom color
        $this->switchTenant($this->tenantA);
        $settingsA = app(BrandingSettings::class);
        $settingsA->primary_color = '#FF0000';
        $settingsA->save();
        $this->forgetSettings();

        // Verify Tenant A sees their custom color
        $this->assertEquals('#FF0000', app(BrandingSettings::class)->primary_color);

        // Tenant B should still see the global default
        $this->switchTenant($this->tenantB);
        $this->assertEquals($globalDefault, app(BrandingSettings::class)->primary_color);
    }

    public function test_tenant_settings_overlay_global_defaults(): void
    {
        // Tenant A overrides only primary_color
        $this->switchTenant($this->tenantA);
        $settings = app(BrandingSettings::class);
        $originalSecondary = $settings->secondary_color;
        $settings->primary_color = '#AABBCC';
        $settings->save();
        $this->forgetSettings();

        // Tenant A should see custom primary but global secondary
        $settings = app(BrandingSettings::class);
        $this->assertEquals('#AABBCC', $settings->primary_color);
        $this->assertEquals($originalSecondary, $settings->secondary_color);
    }

    public function test_reset_tenant_settings_falls_back_to_global_defaults(): void
    {
        // Read global default
        $globalDefault = app(BrandingSettings::class)->primary_color;
        $this->forgetSettings();

        // Tenant A writes custom settings
        $this->switchTenant($this->tenantA);
        $settings = app(BrandingSettings::class);
        $settings->primary_color = '#CUSTOM1';
        $settings->save();
        $this->forgetSettings();

        // Verify custom value is active
        $this->assertEquals('#CUSTOM1', app(BrandingSettings::class)->primary_color);

        // Reset tenant settings (simulating dashboard:reset)
        DB::table('settings')->where('tenant_id', $this->tenantA->id)->delete();
        $this->forgetSettings();

        // Should fall back to global default
        $this->assertEquals($globalDefault, app(BrandingSettings::class)->primary_color);
    }

    public function test_no_tenant_context_returns_global_defaults(): void
    {
        // No Filament tenant set → should return global defaults
        $this->switchTenant(null);

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#0d47a1', $settings->primary_color);
    }

    public function test_global_default_rows_use_sentinel_tenant_id(): void
    {
        // All settings rows created by migrations should have tenant_id = 0 (sentinel)
        $globalRows = DB::table('settings')
            ->where('tenant_id', TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID)
            ->count();
        $this->assertGreaterThan(0, $globalRows);

        // No rows should have a real tenant ID yet
        $tenantRows = DB::table('settings')
            ->where('tenant_id', '>', TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID)
            ->count();
        $this->assertEquals(0, $tenantRows);
    }

    public function test_writing_creates_tenant_specific_rows(): void
    {
        $this->switchTenant($this->tenantA);
        $settings = app(BrandingSettings::class);
        $settings->primary_color = '#TENANT_A';
        $settings->save();

        // Check that tenant-specific rows were created
        $tenantRows = DB::table('settings')
            ->where('tenant_id', $this->tenantA->id)
            ->count();
        $this->assertGreaterThan(0, $tenantRows);

        // Global rows should still exist
        $globalRows = DB::table('settings')
            ->where('tenant_id', TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID)
            ->where('group', 'branding')
            ->count();
        $this->assertGreaterThan(0, $globalRows);
    }

    public function test_both_tenants_can_have_independent_settings(): void
    {
        // Tenant A
        $this->switchTenant($this->tenantA);
        $settingsA = app(BrandingSettings::class);
        $settingsA->primary_color = '#AAA111';
        $settingsA->save();

        // Tenant B
        $this->switchTenant($this->tenantB);
        $settingsB = app(BrandingSettings::class);
        $settingsB->primary_color = '#BBB222';
        $settingsB->save();

        // Verify isolation
        $this->switchTenant($this->tenantA);
        $this->assertEquals('#AAA111', app(BrandingSettings::class)->primary_color);

        $this->switchTenant($this->tenantB);
        $this->assertEquals('#BBB222', app(BrandingSettings::class)->primary_color);
    }

    public function test_general_settings_are_also_tenant_scoped(): void
    {
        // Read global default
        $globalSiteName = app(GeneralSettings::class)->site_name;
        $this->forgetSettings();

        // Tenant A changes site name
        $this->switchTenant($this->tenantA);
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Tenant A Dashboard';
        $settings->save();

        // Tenant B should see global default
        $this->switchTenant($this->tenantB);
        $this->assertEquals($globalSiteName, app(GeneralSettings::class)->site_name);

        // Tenant A should see their custom name
        $this->switchTenant($this->tenantA);
        $this->assertEquals('Tenant A Dashboard', app(GeneralSettings::class)->site_name);
    }
}
