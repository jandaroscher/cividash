<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected ?PageSeeder $seeder = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and authenticate for Filament tenant context
        $user = User::factory()->create();
        $tenant = Tenant::where('slug', 'default')->first();
        if ($tenant) {
            $user->tenants()->sync([$tenant->id]);
            Filament::auth()->login($user);
            Filament::setTenant($tenant);
        }

        $this->seeder = new PageSeeder;
    }

    public function test_page_seeder_creates_pages(): void
    {
        $pageIdMap = $this->seeder->run();

        $this->assertCount(5, $pageIdMap);
        $this->assertArrayHasKey('/', $pageIdMap);
        $this->assertArrayHasKey('kontakt', $pageIdMap);
        $this->assertArrayHasKey('download', $pageIdMap);
        $this->assertArrayHasKey('datenschutz', $pageIdMap);
        $this->assertArrayHasKey('impressum', $pageIdMap);

        $this->assertDatabaseCount('pages', 5);
    }

    public function test_page_seeder_creates_home_page(): void
    {
        $pageIdMap = $this->seeder->run();

        $homePage = Page::find($pageIdMap['/']);
        $this->assertNotNull($homePage);
        $this->assertEquals('landingpage', $homePage->layout);
        $this->assertEquals('Zukunftsbarometer Regensburg', $homePage->getTranslation('title', 'de'));
        $this->assertEquals('Future Barometer Regensburg', $homePage->getTranslation('title', 'en'));
        $this->assertEquals('/', $homePage->getTranslation('slug', 'de'));
        $this->assertEquals('/', $homePage->getTranslation('slug', 'en'));
    }

    public function test_page_seeder_creates_contact_page(): void
    {
        $pageIdMap = $this->seeder->run();

        $contactPage = Page::find($pageIdMap['kontakt']);
        $this->assertNotNull($contactPage);
        $this->assertEquals('subpage', $contactPage->layout);
        $this->assertEquals('Kontakt', $contactPage->getTranslation('title', 'de'));
        $this->assertEquals('Contact', $contactPage->getTranslation('title', 'en'));
        $this->assertEquals('kontakt', $contactPage->getTranslation('slug', 'de'));
        $this->assertEquals('contact', $contactPage->getTranslation('slug', 'en'));
    }

    public function test_page_seeder_creates_download_page(): void
    {
        $pageIdMap = $this->seeder->run();

        $downloadPage = Page::find($pageIdMap['download']);
        $this->assertNotNull($downloadPage);
        $this->assertEquals('subpage', $downloadPage->layout);
        $this->assertEquals('Download', $downloadPage->getTranslation('title', 'de'));
        $this->assertEquals('Download', $downloadPage->getTranslation('title', 'en'));
    }

    public function test_page_seeder_creates_privacy_page(): void
    {
        $pageIdMap = $this->seeder->run();

        $privacyPage = Page::find($pageIdMap['datenschutz']);
        $this->assertNotNull($privacyPage);
        $this->assertEquals('subpage', $privacyPage->layout);
        $this->assertEquals('Datenschutz', $privacyPage->getTranslation('title', 'de'));
        $this->assertEquals('Privacy', $privacyPage->getTranslation('title', 'en'));
        $this->assertEquals('datenschutz', $privacyPage->getTranslation('slug', 'de'));
        $this->assertEquals('privacy', $privacyPage->getTranslation('slug', 'en'));
    }

    public function test_page_seeder_creates_imprint_page(): void
    {
        $pageIdMap = $this->seeder->run();

        $imprintPage = Page::find($pageIdMap['impressum']);
        $this->assertNotNull($imprintPage);
        $this->assertEquals('subpage', $imprintPage->layout);
        $this->assertEquals('Impressum', $imprintPage->getTranslation('title', 'de'));
        $this->assertEquals('Imprint', $imprintPage->getTranslation('title', 'en'));
        $this->assertEquals('impressum', $imprintPage->getTranslation('slug', 'de'));
        $this->assertEquals('imprint', $imprintPage->getTranslation('slug', 'en'));
    }

    public function test_page_seeder_is_idempotent(): void
    {
        // First run
        $pageIdMap1 = $this->seeder->run();
        $count1 = Page::count();

        // Second run
        $pageIdMap2 = $this->seeder->run();
        $count2 = Page::count();

        // Should have same count and same IDs
        $this->assertEquals($count1, $count2);
        $this->assertEquals($pageIdMap1, $pageIdMap2);
    }

    public function test_page_seeder_creates_blocks(): void
    {
        $pageIdMap = $this->seeder->run();

        $homePage = Page::find($pageIdMap['/']);
        $blocks = $homePage->getTranslation('blocks', 'de', false) ?? [];

        $this->assertIsArray($blocks);
        $this->assertGreaterThan(0, count($blocks));

        // Check first block structure
        $firstBlock = $blocks[0];
        $this->assertArrayHasKey('type', $firstBlock);
        $this->assertArrayHasKey('data', $firstBlock);
    }

    public function test_page_seeder_sets_meta_description(): void
    {
        $pageIdMap = $this->seeder->run();

        $homePage = Page::find($pageIdMap['/']);
        $metaDescription = $homePage->getTranslation('meta_description', 'de', false);

        $this->assertNotNull($metaDescription);
        $this->assertStringContainsString('Nachhaltigkeitsmonitoring', $metaDescription);
    }

    public function test_page_seeder_updates_existing_pages(): void
    {
        // Create existing page
        $existing = Page::create([
            'title' => ['de' => 'Old Title', 'en' => 'Old Title EN'],
            'slug' => ['de' => 'kontakt', 'en' => 'contact'],
            'layout' => 'subpage',
            'blocks' => [],
        ]);

        $oldTitle = $existing->getTranslation('title', 'de');

        // Run seeder
        $pageIdMap = $this->seeder->run();

        $existing->refresh();

        // Should update title
        $this->assertNotEquals($oldTitle, $existing->getTranslation('title', 'de'));
        $this->assertEquals('Kontakt', $existing->getTranslation('title', 'de'));
        $this->assertEquals($existing->id, $pageIdMap['kontakt']);
    }

    public function test_page_seeder_handles_bilingual_content(): void
    {
        $pageIdMap = $this->seeder->run();

        $contactPage = Page::find($pageIdMap['kontakt']);

        // Check DE content
        $this->assertEquals('Kontakt', $contactPage->getTranslation('title', 'de'));
        $this->assertEquals('kontakt', $contactPage->getTranslation('slug', 'de'));

        // Check EN content
        $this->assertEquals('Contact', $contactPage->getTranslation('title', 'en'));
        $this->assertEquals('contact', $contactPage->getTranslation('slug', 'en'));
    }
}
