<?php

namespace Tests\Feature;

use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Page;
use App\Models\Tenant;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\PageSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected ?NavigationSeeder $seeder = null;

    protected ?PageSeeder $pageSeeder = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and authenticate for Filament tenant context
        $user = \App\Models\User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();
        if ($tenant) {
            $user->tenants()->sync([$tenant->id]);
            Filament::auth()->login($user);
            Filament::setTenant($tenant);
        }

        $this->seeder = new NavigationSeeder;
        $this->pageSeeder = new PageSeeder;
    }

    public function test_navigation_seeder_creates_header_navigation(): void
    {
        // First seed pages
        $pageIdMap = $this->pageSeeder->run();

        // Then seed navigation
        $this->seeder->run($pageIdMap);

        $navigation = Navigation::getInstance();
        $navigationItems = $navigation->getTranslation('navigation_items', 'de', false) ?? [];

        $this->assertIsArray($navigationItems);
        $this->assertCount(2, $navigationItems);

        // Check Download item
        $downloadItem = collect($navigationItems)->firstWhere('type', 'page');
        $this->assertNotNull($downloadItem);
        $this->assertEquals('page', $downloadItem['type']);
        $this->assertArrayHasKey('page_id', $downloadItem);
        $this->assertArrayHasKey('label', $downloadItem);
        $this->assertArrayHasKey('url', $downloadItem);
    }

    public function test_navigation_seeder_creates_footer_navigation(): void
    {
        // First seed pages
        $pageIdMap = $this->pageSeeder->run();

        // Then seed navigation
        $this->seeder->run($pageIdMap);

        $footer = FooterNavigation::getInstance();
        $navigationItems = $footer->getTranslation('footer_navigation_items', 'de', false) ?? [];

        $this->assertIsArray($navigationItems);
        $this->assertGreaterThanOrEqual(4, count($navigationItems));

        // Check that we have page items
        $pageItems = collect($navigationItems)->where('type', 'page');
        $this->assertGreaterThanOrEqual(2, $pageItems->count());

        // Check that we have manual items
        $manualItems = collect($navigationItems)->where('type', 'manual');
        $this->assertGreaterThanOrEqual(2, $manualItems->count());
    }

    public function test_navigation_seeder_links_to_pages(): void
    {
        // First seed pages
        $pageIdMap = $this->pageSeeder->run();

        // Then seed navigation
        $this->seeder->run($pageIdMap);

        $navigation = Navigation::getInstance();
        $navigationItems = $navigation->getTranslation('navigation_items', 'de', false) ?? [];

        // Find Download page item
        $downloadPage = Page::whereJsonContains('slug->de', 'download')->first();
        $downloadItem = collect($navigationItems)->first(function ($item) use ($downloadPage) {
            return $item['type'] === 'page' && $item['page_id'] === $downloadPage->id;
        });

        $this->assertNotNull($downloadItem);
        $this->assertEquals($downloadPage->id, $downloadItem['page_id']);
    }

    public function test_navigation_seeder_creates_manual_links(): void
    {
        // First seed pages
        $pageIdMap = $this->pageSeeder->run();

        // Then seed navigation
        $this->seeder->run($pageIdMap);

        $footer = FooterNavigation::getInstance();
        $navigationItems = $footer->getTranslation('footer_navigation_items', 'de', false) ?? [];

        // Find regensburg.de link - check in translatable structure
        $regensburgLink = collect($navigationItems)->first(function ($item) {
            return isset($item['url']) && is_array($item['url']) && ($item['url']['de'] ?? '') === 'https://www.regensburg.de/';
        });
        $this->assertNotNull($regensburgLink);
        $this->assertEquals('manual', $regensburgLink['type']);
        $this->assertIsArray($regensburgLink['label']);
        $this->assertEquals('regensburg.de', $regensburgLink['label']['de'] ?? '');
        $this->assertNull($regensburgLink['page_id'] ?? null);

        // Find mein.regensburg.de link
        $meinRegensburgLink = collect($navigationItems)->first(function ($item) {
            return isset($item['url']) && is_array($item['url']) && ($item['url']['de'] ?? '') === 'https://mein.regensburg.de/';
        });
        $this->assertNotNull($meinRegensburgLink);
        $this->assertEquals('manual', $meinRegensburgLink['type']);
        $this->assertIsArray($meinRegensburgLink['label']);
        $this->assertEquals('mein.regensburg.de', $meinRegensburgLink['label']['de'] ?? '');
    }

    public function test_navigation_seeder_is_idempotent(): void
    {
        // First seed pages
        $pageIdMap = $this->pageSeeder->run();

        // First run
        $this->seeder->run($pageIdMap);
        $navigation1 = Navigation::getInstance();
        $footer1 = FooterNavigation::getInstance();
        $headerItems1 = $navigation1->getTranslation('navigation_items', 'de', false) ?? [];
        $footerItems1 = $footer1->getTranslation('footer_navigation_items', 'de', false) ?? [];

        // Second run
        $this->seeder->run($pageIdMap);
        $navigation2 = Navigation::getInstance();
        $footer2 = FooterNavigation::getInstance();
        $headerItems2 = $navigation2->getTranslation('navigation_items', 'de', false) ?? [];
        $footerItems2 = $footer2->getTranslation('footer_navigation_items', 'de', false) ?? [];

        // Should skip if already exists
        // Since the seeder checks if navigation already exists, second run should not change anything
        $this->assertEquals(count($headerItems1), count($headerItems2));
        $this->assertEquals(count($footerItems1), count($footerItems2));
    }

    public function test_navigation_seeder_handles_missing_pages_gracefully(): void
    {
        // Run with empty page map
        $emptyPageMap = [];

        // Should not throw exception
        $this->seeder->run($emptyPageMap);

        $navigation = Navigation::getInstance();
        $footer = FooterNavigation::getInstance();

        // Navigation should be empty or not set
        $this->assertIsArray($navigation->getTranslation('navigation_items', 'de', false) ?? []);
        $this->assertIsArray($footer->getTranslation('footer_navigation_items', 'de', false) ?? []);
    }

    public function test_navigation_seeder_sets_correct_labels(): void
    {
        // First seed pages
        $pageIdMap = $this->pageSeeder->run();

        // Then seed navigation
        $this->seeder->run($pageIdMap);

        $navigation = Navigation::getInstance();
        $navigationItems = $navigation->getTranslation('navigation_items', 'de', false) ?? [];

        // Find Download item
        $downloadPage = Page::whereJsonContains('slug->de', 'download')->first();
        $downloadItem = collect($navigationItems)->first(function ($item) use ($downloadPage) {
            return $item['type'] === 'page' && $item['page_id'] === $downloadPage->id;
        });

        $this->assertNotNull($downloadItem);
        $this->assertIsArray($downloadItem['label']);
        $this->assertEquals('Download', $downloadItem['label']['de'] ?? '');

        // Find Kontakt item
        $contactPage = Page::whereJsonContains('slug->de', 'kontakt')->first();
        $contactItem = collect($navigationItems)->first(function ($item) use ($contactPage) {
            return $item['type'] === 'page' && $item['page_id'] === $contactPage->id;
        });

        $this->assertNotNull($contactItem);
        $this->assertIsArray($contactItem['label']);
        $this->assertEquals('Kontakt', $contactItem['label']['de'] ?? '');
    }
}
