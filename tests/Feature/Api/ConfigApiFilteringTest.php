<?php

namespace Tests\Feature\Api;

use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigApiFilteringTest extends TestCase
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

        // Reset the static cache for pageHasIsPublic between tests
        $reflection = new \ReflectionClass(\App\Http\Controllers\Api\ConfigController::class);
        $prop = $reflection->getProperty('pageHasIsPublic');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_header_excludes_inactive_navigation_items(): void
    {
        Navigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'navigation_items' => [
                    'de' => [
                        ['label' => 'Aktiv', 'url' => '/aktiv', 'is_active' => true],
                        ['label' => 'Inaktiv', 'url' => '/inaktiv', 'is_active' => false],
                        ['label' => 'Ohne Flag', 'url' => '/ohne-flag'],
                    ],
                    'en' => [],
                ],
                'show_language_switcher' => true,
                'dropdown_enabled' => false,
            ]
        );

        $response = $this->getJson('/api/config/header?locale=de');

        $response->assertOk();
        $items = $response->json('data.navigation_items');

        $labels = array_column($items, 'label');
        $this->assertContains('Aktiv', $labels);
        $this->assertNotContains('Inaktiv', $labels);
        // Items without is_active flag should be preserved
        $this->assertContains('Ohne Flag', $labels);
    }

    public function test_footer_excludes_inactive_items(): void
    {
        FooterNavigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'footer_navigation_items' => [
                    'de' => [
                        ['label' => 'Impressum', 'url' => '/impressum', 'is_active' => true],
                        ['label' => 'Versteckt', 'url' => '/versteckt', 'is_active' => false],
                    ],
                    'en' => [],
                ],
                'social_links' => ['de' => [], 'en' => []],
                'copyright_text' => ['de' => '', 'en' => ''],
                'layout_type' => 'single-row',
                'columns' => 3,
                'social_links_enabled' => false,
            ]
        );

        $response = $this->getJson('/api/config/footer?locale=de');

        $response->assertOk();
        $items = $response->json('data.footer_navigation_items');

        $labels = array_column($items, 'label');
        $this->assertContains('Impressum', $labels);
        $this->assertNotContains('Versteckt', $labels);
    }

    public function test_header_excludes_items_referencing_non_public_pages(): void
    {
        $publicPage = Page::factory()->forTenant($this->tenant)->create([
            'is_public' => true,
        ]);
        $privatePage = Page::factory()->forTenant($this->tenant)->create([
            'is_public' => false,
        ]);

        Navigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'navigation_items' => [
                    'de' => [
                        ['label' => 'Oeffentlich', 'type' => 'page', 'page_id' => $publicPage->id, 'is_active' => true],
                        ['label' => 'Privat', 'type' => 'page', 'page_id' => $privatePage->id, 'is_active' => true],
                        ['label' => 'Externer Link', 'type' => 'url', 'url' => 'https://example.com', 'is_active' => true],
                    ],
                    'en' => [],
                ],
                'show_language_switcher' => true,
                'dropdown_enabled' => false,
            ]
        );

        $response = $this->getJson('/api/config/header?locale=de');

        $response->assertOk();
        $items = $response->json('data.navigation_items');

        $labels = array_column($items, 'label');
        $this->assertContains('Oeffentlich', $labels);
        $this->assertNotContains('Privat', $labels);
        $this->assertContains('Externer Link', $labels);
    }

    public function test_nested_children_with_inactive_items_filtered(): void
    {
        Navigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'navigation_items' => [
                    'de' => [
                        [
                            'label' => 'Eltern',
                            'url' => '/eltern',
                            'is_active' => true,
                            'children' => [
                                ['label' => 'Kind Aktiv', 'url' => '/kind-aktiv', 'is_active' => true],
                                ['label' => 'Kind Inaktiv', 'url' => '/kind-inaktiv', 'is_active' => false],
                            ],
                        ],
                    ],
                    'en' => [],
                ],
                'show_language_switcher' => true,
                'dropdown_enabled' => false,
            ]
        );

        $response = $this->getJson('/api/config/header?locale=de');

        $response->assertOk();
        $items = $response->json('data.navigation_items');

        $this->assertCount(1, $items);
        $this->assertEquals('Eltern', $items[0]['label']);

        $children = $items[0]['children'];
        $childLabels = array_column($children, 'label');
        $this->assertContains('Kind Aktiv', $childLabels);
        $this->assertNotContains('Kind Inaktiv', $childLabels);
    }

    public function test_footer_social_links_exclude_inactive(): void
    {
        FooterNavigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'footer_navigation_items' => ['de' => [], 'en' => []],
                'social_links' => [
                    'de' => [
                        ['platform' => 'twitter', 'url' => 'https://twitter.com/test', 'is_active' => true],
                        ['platform' => 'facebook', 'url' => 'https://facebook.com/test', 'is_active' => false],
                    ],
                    'en' => [],
                ],
                'copyright_text' => ['de' => '', 'en' => ''],
                'layout_type' => 'single-row',
                'columns' => 3,
                'social_links_enabled' => true,
            ]
        );

        $response = $this->getJson('/api/config/footer?locale=de');

        $response->assertOk();
        $socialLinks = $response->json('data.social_links');

        $platforms = array_column($socialLinks, 'platform');
        $this->assertContains('twitter', $platforms);
        $this->assertNotContains('facebook', $platforms);
    }

    public function test_header_returns_language_switcher_and_dropdown_settings(): void
    {
        Navigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'navigation_items' => ['de' => [], 'en' => []],
                'show_language_switcher' => false,
                'dropdown_enabled' => true,
            ]
        );

        $response = $this->getJson('/api/config/header?locale=de');

        $response->assertOk()
            ->assertJsonPath('data.show_language_switcher', false)
            ->assertJsonPath('data.dropdown_enabled', true);
    }

    public function test_footer_returns_layout_and_copyright(): void
    {
        FooterNavigation::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'footer_navigation_items' => ['de' => [], 'en' => []],
                'social_links' => ['de' => [], 'en' => []],
                'copyright_text' => ['de' => '2025 Stadt', 'en' => '2025 City'],
                'layout_type' => 'columns',
                'columns' => 4,
                'social_links_enabled' => false,
            ]
        );

        $response = $this->getJson('/api/config/footer?locale=de');

        $response->assertOk()
            ->assertJsonPath('data.layout_type', 'columns')
            ->assertJsonPath('data.columns', 4)
            ->assertJsonPath('data.social_links_enabled', false)
            ->assertJsonPath('data.copyright_text', '2025 Stadt');
    }
}
