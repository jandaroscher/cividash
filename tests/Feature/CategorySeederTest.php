<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Services\DashboardJsonParser;
use App\Services\MediaDownloadService;
use App\Services\ParsedCategory;
use Database\Seeders\CategorySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    protected ?CategorySeeder $seeder = null;

    protected function setUp(): void
    {
        parent::setUp();
        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant']
        );

        // Create user and authenticate for Filament tenant context
        $user = \App\Models\User::factory()->create();
        $user->tenants()->sync([$defaultTenant->id]);
        Filament::auth()->login($user);
        Filament::setTenant($defaultTenant);
        
        $this->seeder = new CategorySeeder(new MediaDownloadService());
    }

    public function test_category_seeder_creates_categories(): void
    {
        $categories = collect([
            new ParsedCategory(id: 1, title: 'Partizipation und Teilhabe'),
            new ParsedCategory(id: 2, title: 'Digitalisierung'),
        ]);

        $idMap = $this->seeder->run($categories);
        $group = CategoryGroup::where('key', 'fields')->first();

        $this->assertCount(2, $idMap);
        $this->assertArrayHasKey(1, $idMap);
        $this->assertArrayHasKey(2, $idMap);
        $this->assertNotNull($group);

        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseHas('categories', [
            'id' => $idMap[1],
        ]);
        $this->assertDatabaseHas('categories', [
            'id' => $idMap[2],
        ]);
        $this->assertDatabaseHas('categories', [
            'id' => $idMap[1],
            'category_group_id' => $group->id,
        ]);
    }

    public function test_category_seeder_is_idempotent(): void
    {
        $categories = collect([
            new ParsedCategory(id: 1, title: 'Partizipation und Teilhabe'),
            new ParsedCategory(id: 2, title: 'Digitalisierung'),
        ]);

        // First run
        $idMap1 = $this->seeder->run($categories);
        $count1 = Category::count();

        // Second run with same data
        $idMap2 = $this->seeder->run($categories);
        $count2 = Category::count();

        // Should have same count and same IDs
        $this->assertEquals($count1, $count2);
        $this->assertEquals($idMap1, $idMap2);
    }

    public function test_category_seeder_handles_translatable_slug(): void
    {
        $categories = collect([
            new ParsedCategory(id: 1, title: 'Test Category'),
        ]);

        $idMap = $this->seeder->run($categories);
        $category = Category::find($idMap[1]);
        $this->assertNotNull($category);

        // Slug should be translatable
        $this->assertEquals('Test Category', $category->getTranslation('slug', 'de'));
        // EN translation may be empty string or null if not set
        $enSlug = $category->getTranslation('slug', 'en');
        $this->assertTrue($enSlug === null || $enSlug === '');
    }

    public function test_category_seeder_sets_source_hash_and_last_synced_at(): void
    {
        $categories = collect([
            new ParsedCategory(id: 1, title: 'Test Category'),
        ]);

        $idMap = $this->seeder->run($categories);
        $category = Category::find($idMap[1]);

        $this->assertNotNull($category->source_hash);
        $this->assertNotNull($category->last_synced_at);
        $this->assertEquals(64, strlen($category->source_hash)); // SHA256 is 64 chars
    }

    public function test_category_seeder_updates_existing_categories(): void
    {
        // Create existing category with slug matching the incoming ParsedCategory title
        // so the seeder can find and update it
        $existing = Category::create([
            'slug' => ['de' => 'New Title', 'en' => null],
            'position' => 0,
        ]);

        $oldHash = $existing->source_hash;
        $oldSynced = $existing->last_synced_at;

        // Update with same title (but different source data will change hash)
        $categories = collect([
            new ParsedCategory(id: 1, title: 'New Title'),
        ]);

        $idMap = $this->seeder->run($categories);

        $existing->refresh();

        // Should still have the same slug
        $this->assertEquals('New Title', $existing->getTranslation('slug', 'de'));
        // Should update hash and synced_at when source data changes
        $this->assertNotEquals($oldHash, $existing->source_hash);
        $this->assertNotEquals($oldSynced, $existing->last_synced_at);
    }

    public function test_category_seeder_sets_position_correctly(): void
    {
        $categories = collect([
            new ParsedCategory(id: 1, title: 'First'),
            new ParsedCategory(id: 2, title: 'Second'),
            new ParsedCategory(id: 3, title: 'Third'),
        ]);

        $this->seeder->run($categories);

        $first = Category::whereJsonContains('slug->de', 'First')->first();
        $second = Category::whereJsonContains('slug->de', 'Second')->first();
        $third = Category::whereJsonContains('slug->de', 'Third')->first();

        $this->assertEquals(0, $first->position);
        $this->assertEquals(1, $second->position);
        $this->assertEquals(2, $third->position);
    }
}

