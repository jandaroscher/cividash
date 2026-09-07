<?php

namespace Tests\Feature\Theme;

use App\Models\Tenant;
use App\Models\Theme;
use App\Settings\TenantAwareDatabaseSettingsRepository;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tests\TestCase;

/**
 * E2E proof for the "5 dashboards share one theme, 1 uses a modified
 * configuration" use-case (Weg B), in miniature with 2 tenants.
 */
class ThemeSharingE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        parent::tearDown();
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

    public function test_shared_theme_with_one_tenant_override_resolves_correctly_and_does_not_leak(): void
    {
        $theme = Theme::factory()->create([
            'settings' => ['branding' => [
                'accent_color' => '#222222',
                'primary_color' => '#333333',
            ]],
        ]);
        $tenantA = Tenant::factory()->create(['theme_id' => $theme->id]);
        $tenantB = Tenant::factory()->create(['theme_id' => $theme->id]);
        $this->setTenantOverride($tenantB, 'accent_color', '#999999');

        $propsA = $this->resolveBranding($tenantA);
        $propsB = $this->resolveBranding($tenantB);

        // (1) Tenant A (no override) sees both theme values.
        $this->assertSame('#222222', $propsA['accent_color']);
        $this->assertSame('#333333', $propsA['primary_color']);

        // (2) Tenant B sees its override but still inherits the other theme value.
        $this->assertSame('#999999', $propsB['accent_color']);
        $this->assertSame('#333333', $propsB['primary_color']);

        // (3) No cross-tenant leak: B's override never shows up for A, and the
        // two resolutions differ in exactly the one overridden key.
        $this->assertNotSame($propsA['accent_color'], $propsB['accent_color']);
        $this->assertSame($propsA['primary_color'], $propsB['primary_color']);
    }
}
