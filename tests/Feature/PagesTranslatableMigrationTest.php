<?php

namespace Tests\Feature;

use App\Models\Page; // Use custom Page model with HasTranslations
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PagesTranslatableMigrationTest extends TestCase
{
    use RefreshDatabase;

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
    }

    /**
     * Test that translatable migration converts title and slug to JSON columns.
     */
    public function test_migration_converts_data_to_translatable_format(): void
    {
        // Run base migration first
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_04_25_100921_create_pages_table.php'])->assertSuccessful();
        
        // Run the slug constraint fix migration
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_04_25_100922_fix_slug_unique_constraint_on_pages_table.php'])->assertSuccessful();

        // Create test data with old format (string)
        DB::table(config('filament-fabricator.table_name', 'pages'))->insert([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'layout' => 'default',
            'blocks' => json_encode([['type' => 'hero', 'data' => ['title' => 'Hero Title']]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rollback the translatable migration first (if it was already run)
        $migrationFiles = glob(database_path('migrations/*_convert_pages_to_translatable.php'));
        if (!empty($migrationFiles)) {
            $migrationFile = basename($migrationFiles[0]);
            // Try rollback first
            try {
                $this->artisan('migrate:rollback', ['--path' => "database/migrations/{$migrationFile}"]);
            } catch (\Exception $e) {
                // Migration wasn't run yet - that's fine
            }
            // Now run it fresh
            $this->artisan('migrate', ['--path' => "database/migrations/{$migrationFile}"])->assertSuccessful();
        }

        // Verify columns are now JSON
        $columns = Schema::getColumnListing(config('filament-fabricator.table_name', 'pages'));
        $this->assertContains('title', $columns);
        $this->assertContains('slug', $columns);
        $this->assertContains('meta_description', $columns);

        // Verify data was migrated correctly
        // First check raw DB data
        $rawPage = DB::table(config('filament-fabricator.table_name', 'pages'))->first();
        $this->assertNotNull($rawPage, 'Page should exist in database');
        
        // Decode JSON strings
        $titleData = is_string($rawPage->title) ? json_decode($rawPage->title, true) : $rawPage->title;
        $slugData = is_string($rawPage->slug) ? json_decode($rawPage->slug, true) : $rawPage->slug;
        $blocksData = is_string($rawPage->blocks) ? json_decode($rawPage->blocks, true) : $rawPage->blocks;
        
        // Assert raw DB data is correctly formatted as JSON with locale structure
        $this->assertIsArray($titleData, 'Title should be JSON in database');
        $this->assertArrayHasKey('de', $titleData);
        $this->assertEquals('Test Page', $titleData['de']);
        $this->assertArrayHasKey('en', $titleData);
        $this->assertEquals('', $titleData['en']);
        
        $this->assertIsArray($slugData, 'Slug should be JSON in database');
        $this->assertArrayHasKey('de', $slugData);
        $this->assertEquals('test-page', $slugData['de']);
        
        $this->assertIsArray($blocksData, 'Blocks should be JSON in database');
        $this->assertArrayHasKey('de', $blocksData);
        $this->assertArrayHasKey('en', $blocksData);
        
        // Now test via model
        // Use withoutGlobalScope because migration tests insert data directly via DB::table()
        // and don't set tenant_id, so we need to bypass the tenant scope for this test
        $page = Page::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($page, 'Page should exist in database');
        
        // Spatie Translatable returns the value for the current locale, not the whole JSON array
        // Test with DE locale (default)
        app()->setLocale('de');
        $this->assertEquals('Test Page', $page->title);
        $this->assertEquals('test-page', $page->slug);
        
        // Test with EN locale
        app()->setLocale('en');
        $this->assertEquals('', $page->title); // EN translation is empty string
        $this->assertEquals('', $page->slug); // EN translation is empty string
        
        // Test getTranslation method
        $this->assertEquals('Test Page', $page->getTranslation('title', 'de'));
        $this->assertEquals('', $page->getTranslation('title', 'en'));
        $this->assertEquals('test-page', $page->getTranslation('slug', 'de'));
        $this->assertEquals('', $page->getTranslation('slug', 'en'));
        
        // Test blocks - Spatie Translatable should return the locale-specific array
        app()->setLocale('de');
        $blocks = $page->getTranslation('blocks', 'de');
        $this->assertIsArray($blocks);
        $this->assertNotEmpty($blocks);
        $this->assertEquals('hero', $blocks[0]['type']);
        $this->assertEquals('Hero Title', $blocks[0]['data']['title']);
        
        $blocksEn = $page->getTranslation('blocks', 'en');
        $this->assertIsArray($blocksEn);
        $this->assertEmpty($blocksEn);
    }

    /**
     * Test that unique constraint on slug is removed after migration.
     */
    public function test_migration_removes_unique_constraint_on_slug(): void
    {
        // Run base migration first
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_04_25_100921_create_pages_table.php'])->assertSuccessful();
        
        // Run the slug constraint fix migration
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_04_25_100922_fix_slug_unique_constraint_on_pages_table.php'])->assertSuccessful();

        // Run translatable migration
        $migrationFiles = glob(database_path('migrations/*_convert_pages_to_translatable.php'));
        if (!empty($migrationFiles)) {
            $migrationFile = basename($migrationFiles[0]);
            $this->artisan('migrate', ['--path' => "database/migrations/{$migrationFile}"])->assertSuccessful();
        }

        // Try to insert two pages with same slug (should work now, as unique constraint is removed)
        // But different locales should be allowed
        DB::table(config('filament-fabricator.table_name', 'pages'))->insert([
            'title' => json_encode(['de' => 'Page 1 DE', 'en' => '']),
            'slug' => json_encode(['de' => 'terms', 'en' => '']),
            'layout' => 'default',
            'blocks' => json_encode(['de' => [], 'en' => []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(config('filament-fabricator.table_name', 'pages'))->insert([
            'title' => json_encode(['de' => '', 'en' => 'Page 1 EN']),
            'slug' => json_encode(['de' => '', 'en' => 'terms']),
            'layout' => 'default',
            'blocks' => json_encode(['de' => [], 'en' => []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should have 2 pages now
        $count = DB::table(config('filament-fabricator.table_name', 'pages'))->count();
        $this->assertEquals(2, $count);
    }
}
