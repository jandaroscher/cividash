<?php

namespace Tests\Feature;

use App\Models\Navigation;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSingletonTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_get_instance_returns_record_for_default_tenant(): void
    {
        // Create a navigation record for default tenant
        Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => ['de' => [['label' => 'Start', 'url' => '/']], 'en' => []],

            'dropdown_enabled' => false,
        ]);

        $instance = Navigation::getInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($this->tenant->id, $instance->tenant_id);
    }

    public function test_get_instance_creates_via_get_or_create_for_empty_state(): void
    {
        // No navigation exists yet
        $this->assertDatabaseMissing('navigations', ['tenant_id' => $this->tenant->id]);

        $instance = Navigation::getInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($this->tenant->id, $instance->tenant_id);
        $this->assertDatabaseHas('navigations', ['tenant_id' => $this->tenant->id]);
    }

    public function test_get_instance_returns_record_for_non_default_tenant(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        Filament::setTenant($otherTenant);

        Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'navigation_items' => ['de' => [], 'en' => []],

            'dropdown_enabled' => false,
        ]);

        $instance = Navigation::getInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($otherTenant->id, $instance->tenant_id);
    }

    public function test_get_or_create_instance_creates_when_none_exists(): void
    {
        $this->assertDatabaseMissing('navigations', ['tenant_id' => $this->tenant->id]);

        $instance = Navigation::getOrCreateInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($this->tenant->id, $instance->tenant_id);
        $this->assertFalse($instance->dropdown_enabled);
    }

    public function test_get_or_create_instance_returns_existing_record(): void
    {
        $existing = Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => ['de' => [['label' => 'Existing', 'url' => '/existing']], 'en' => []],

            'dropdown_enabled' => true,
        ]);

        $instance = Navigation::getOrCreateInstance();

        $this->assertEquals($existing->tenant_id, $instance->tenant_id);
        $this->assertTrue($instance->dropdown_enabled);
    }

    public function test_get_translated_items_returns_locale_items(): void
    {
        $nav = Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => [
                'de' => [['label' => 'Startseite', 'url' => '/']],
                'en' => [['label' => 'Home', 'url' => '/']],
            ],

            'dropdown_enabled' => false,
        ]);

        $deItems = $nav->getTranslatedNavigationItems('de');
        $enItems = $nav->getTranslatedNavigationItems('en');

        $this->assertCount(1, $deItems);
        $this->assertEquals('Startseite', $deItems[0]['label']);

        $this->assertCount(1, $enItems);
        $this->assertEquals('Home', $enItems[0]['label']);
    }

    public function test_get_translated_items_handles_translatable_label(): void
    {
        $nav = Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => [
                'de' => [
                    [
                        'label' => ['de' => 'Start', 'en' => 'Home'],
                        'url' => '/',
                    ],
                ],
                'en' => [
                    [
                        'label' => ['de' => 'Start', 'en' => 'Home'],
                        'url' => '/',
                    ],
                ],
            ],

            'dropdown_enabled' => false,
        ]);

        $deItems = $nav->getTranslatedNavigationItems('de');
        $this->assertEquals('Start', $deItems[0]['label']);

        $enItems = $nav->getTranslatedNavigationItems('en');
        $this->assertEquals('Home', $enItems[0]['label']);
    }

    public function test_get_translated_items_falls_back_to_de(): void
    {
        $nav = Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => [
                'de' => [['label' => 'Startseite', 'url' => '/']],
                'en' => [],
            ],

            'dropdown_enabled' => false,
        ]);

        $enItems = $nav->getTranslatedNavigationItems('en');

        // Should fall back to 'de' items since 'en' is empty
        $this->assertCount(1, $enItems);
        $this->assertEquals('Startseite', $enItems[0]['label']);
    }

    public function test_get_translated_items_translates_children(): void
    {
        $nav = Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => [
                'de' => [
                    [
                        'label' => 'Eltern',
                        'url' => '/eltern',
                        'children' => [
                            [
                                'label' => ['de' => 'Kind DE', 'en' => 'Child EN'],
                                'url' => ['de' => '/kind', 'en' => '/child'],
                            ],
                        ],
                    ],
                ],
                'en' => [],
            ],

            'dropdown_enabled' => false,
        ]);

        $items = $nav->getTranslatedNavigationItems('de');

        $this->assertCount(1, $items);
        $this->assertEquals('Eltern', $items[0]['label']);
        $this->assertCount(1, $items[0]['children']);
        $this->assertEquals('Kind DE', $items[0]['children'][0]['label']);
        $this->assertEquals('/kind', $items[0]['children'][0]['url']);
    }

    public function test_get_translated_items_returns_empty_for_null_items(): void
    {
        $nav = Navigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'navigation_items' => null,

            'dropdown_enabled' => false,
        ]);

        $items = $nav->getTranslatedNavigationItems('de');

        $this->assertIsArray($items);
        $this->assertEmpty($items);
    }
}
