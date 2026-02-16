<?php

namespace Tests\Feature;

use App\Models\FooterNavigation;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterNavigationSingletonTest extends TestCase
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
        FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [['label' => 'Impressum', 'url' => '/impressum']], 'en' => []],
            'social_links' => ['de' => [], 'en' => []],
            'copyright_text' => ['de' => 'Test', 'en' => ''],
            'layout_type' => 'single-row',
            'columns' => 3,
            'social_links_enabled' => true,
        ]);

        $instance = FooterNavigation::getInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($this->tenant->id, $instance->tenant_id);
    }

    public function test_get_or_create_instance_creates_with_defaults(): void
    {
        $this->assertDatabaseMissing('footer_navigations', ['tenant_id' => $this->tenant->id]);

        $instance = FooterNavigation::getOrCreateInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($this->tenant->id, $instance->tenant_id);
        $this->assertEquals('single-row', $instance->layout_type);
        $this->assertEquals(3, $instance->columns);
        $this->assertTrue($instance->social_links_enabled);
    }

    public function test_get_or_create_instance_returns_existing(): void
    {
        FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => ['de' => [], 'en' => []],
            'copyright_text' => ['de' => 'Existing', 'en' => ''],
            'layout_type' => 'columns',
            'columns' => 4,
            'social_links_enabled' => false,
        ]);

        $instance = FooterNavigation::getOrCreateInstance();

        $this->assertEquals('columns', $instance->layout_type);
        $this->assertEquals(4, $instance->columns);
        $this->assertFalse($instance->social_links_enabled);
    }

    public function test_translated_footer_items_returns_locale(): void
    {
        $footer = FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => [
                'de' => [['label' => 'Impressum', 'url' => '/impressum']],
                'en' => [['label' => 'Imprint', 'url' => '/imprint']],
            ],
            'social_links' => ['de' => [], 'en' => []],
            'copyright_text' => ['de' => '', 'en' => ''],
        ]);

        $deItems = $footer->getTranslatedFooterNavigationItems('de');
        $enItems = $footer->getTranslatedFooterNavigationItems('en');

        $this->assertCount(1, $deItems);
        $this->assertEquals('Impressum', $deItems[0]['label']);

        $this->assertCount(1, $enItems);
        $this->assertEquals('Imprint', $enItems[0]['label']);
    }

    public function test_translated_social_links_returns_locale(): void
    {
        $footer = FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => [
                'de' => [
                    ['platform' => 'twitter', 'url' => 'https://twitter.com/test', 'title' => ['de' => 'Twitter DE', 'en' => 'Twitter EN']],
                ],
                'en' => [
                    ['platform' => 'twitter', 'url' => 'https://twitter.com/test', 'title' => ['de' => 'Twitter DE', 'en' => 'Twitter EN']],
                ],
            ],
            'copyright_text' => ['de' => '', 'en' => ''],
        ]);

        $deLinks = $footer->getTranslatedSocialLinks('de');
        $enLinks = $footer->getTranslatedSocialLinks('en');

        $this->assertCount(1, $deLinks);
        $this->assertEquals('Twitter DE', $deLinks[0]['title']);

        $this->assertCount(1, $enLinks);
        $this->assertEquals('Twitter EN', $enLinks[0]['title']);
    }

    public function test_translated_copyright_text_returns_locale(): void
    {
        $footer = FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => ['de' => [], 'en' => []],
            'copyright_text' => ['de' => '2025 Stadt Regensburg', 'en' => '2025 City of Regensburg'],
        ]);

        $deCopyright = $footer->getTranslatedCopyrightText('de');
        $enCopyright = $footer->getTranslatedCopyrightText('en');

        $this->assertEquals('2025 Stadt Regensburg', $deCopyright);
        $this->assertEquals('2025 City of Regensburg', $enCopyright);
    }

    public function test_translated_copyright_text_falls_back_to_de(): void
    {
        $footer = FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => ['de' => [], 'en' => []],
            'copyright_text' => ['de' => '2025 Stadt Regensburg', 'en' => ''],
        ]);

        $enCopyright = $footer->getTranslatedCopyrightText('en');

        $this->assertEquals('2025 Stadt Regensburg', $enCopyright);
    }

    public function test_translated_footer_items_translates_labels(): void
    {
        $footer = FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => [
                'de' => [
                    ['label' => ['de' => 'Impressum', 'en' => 'Imprint'], 'url' => '/impressum'],
                ],
                'en' => [
                    ['label' => ['de' => 'Impressum', 'en' => 'Imprint'], 'url' => '/imprint'],
                ],
            ],
            'social_links' => ['de' => [], 'en' => []],
            'copyright_text' => ['de' => '', 'en' => ''],
        ]);

        $deItems = $footer->getTranslatedFooterNavigationItems('de');
        $this->assertEquals('Impressum', $deItems[0]['label']);

        $enItems = $footer->getTranslatedFooterNavigationItems('en');
        $this->assertEquals('Imprint', $enItems[0]['label']);
    }

    public function test_translated_social_links_falls_back_to_de(): void
    {
        $footer = FooterNavigation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'footer_navigation_items' => ['de' => [], 'en' => []],
            'social_links' => [
                'de' => [
                    ['platform' => 'twitter', 'url' => 'https://twitter.com/test', 'title' => 'Twitter'],
                ],
                'en' => [],
            ],
            'copyright_text' => ['de' => '', 'en' => ''],
        ]);

        $enLinks = $footer->getTranslatedSocialLinks('en');

        // Should fall back to 'de' since 'en' is empty
        $this->assertCount(1, $enLinks);
        $this->assertEquals('twitter', $enLinks[0]['platform']);
    }

    public function test_get_instance_creates_for_non_default_tenant(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        Filament::setTenant($otherTenant);

        // No footer navigation exists
        $this->assertDatabaseMissing('footer_navigations', ['tenant_id' => $otherTenant->id]);

        $instance = FooterNavigation::getInstance();

        $this->assertNotNull($instance);
        $this->assertEquals($otherTenant->id, $instance->tenant_id);
    }
}
