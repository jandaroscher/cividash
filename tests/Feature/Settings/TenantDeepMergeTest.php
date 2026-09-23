<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Settings\TenantAwareDatabaseSettingsRepository;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tests\TestCase;

class TenantDeepMergeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        parent::tearDown();
    }

    public function test_tenant_partial_array_override_keeps_global_subkeys(): void
    {
        // global slider_colors (tenant_id=0)
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'slider_colors', 'tenant_id' => 0],
            ['payload' => json_encode(['rail' => '#eee', 'handle' => '#111', 'handle_border' => '#000'])],
        );

        $tenant = Tenant::factory()->create(['slug' => 'demo-city']);
        DB::table('settings')->insert([
            'group' => 'branding', 'name' => 'slider_colors', 'tenant_id' => $tenant->id,
            'payload' => json_encode(['handle' => '#d00000']), // nur ein Sub-Key
        ]);

        Filament::setTenant($tenant, isQuiet: true);

        /** @var TenantAwareDatabaseSettingsRepository $repo */
        $repo = SettingsRepositoryFactory::create();
        $props = $repo->getPropertiesInGroup('branding');

        $this->assertSame(
            ['rail' => '#eee', 'handle' => '#d00000', 'handle_border' => '#000'],
            $props['slider_colors']
        );
    }

    public function test_tenant_shorter_list_value_replaces_global_wholesale(): void
    {
        // global typography_font_weights (tenant_id=0)
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'typography_font_weights', 'tenant_id' => 0],
            ['payload' => json_encode([400, 600, 700])],
        );

        $tenant = Tenant::factory()->create(['slug' => 'demo-city']);
        DB::table('settings')->insert([
            'group' => 'branding', 'name' => 'typography_font_weights', 'tenant_id' => $tenant->id,
            'payload' => json_encode([400]), // shorter list, must replace the global one entirely
        ]);

        Filament::setTenant($tenant, isQuiet: true);

        /** @var TenantAwareDatabaseSettingsRepository $repo */
        $repo = SettingsRepositoryFactory::create();
        $props = $repo->getPropertiesInGroup('branding');

        $this->assertSame([400], $props['typography_font_weights']);
    }

    public function test_tenant_nested_list_override_replaces_global_wholesale(): void
    {
        // global with a nested list under an associative key
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => 'breakpoints', 'tenant_id' => 0],
            ['payload' => json_encode(['sizes' => ['sm', 'md', 'lg', 'xl']])],
        );

        $tenant = Tenant::factory()->create(['slug' => 'demo-city']);
        DB::table('settings')->insert([
            'group' => 'branding', 'name' => 'breakpoints', 'tenant_id' => $tenant->id,
            'payload' => json_encode(['sizes' => ['lg']]), // shorter nested list, must replace wholesale
        ]);

        Filament::setTenant($tenant, isQuiet: true);

        /** @var TenantAwareDatabaseSettingsRepository $repo */
        $repo = SettingsRepositoryFactory::create();
        $props = $repo->getPropertiesInGroup('branding');

        $this->assertSame(['sizes' => ['lg']], $props['breakpoints']);
    }
}
