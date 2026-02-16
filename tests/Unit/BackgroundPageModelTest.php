<?php

namespace Tests\Unit;

use App\Models\BackgroundPage;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundPageModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'default')->first();
        $user = User::factory()->create();
        $user->tenants()->syncWithoutDetaching($this->tenant->id);
        $this->actingAs($user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_belongs_to_tile(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $backgroundPage = BackgroundPage::create([
            'slug' => ['de' => 'test-slug', 'en' => 'test-slug-en'],
            'content' => ['de' => '<p>Inhalt</p>', 'en' => '<p>Content</p>'],
            'position' => 1,
            'tile_id' => $tile->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertNotNull($backgroundPage->tile);
        $this->assertEquals($tile->id, $backgroundPage->tile->id);
    }

    public function test_belongs_to_tenant(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $backgroundPage = BackgroundPage::create([
            'slug' => ['de' => 'tenant-test', 'en' => 'tenant-test-en'],
            'content' => ['de' => '<p>Test</p>', 'en' => '<p>Test</p>'],
            'position' => 0,
            'tile_id' => $tile->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertNotNull($backgroundPage->tenant);
        $this->assertEquals($this->tenant->id, $backgroundPage->tenant->id);
    }

    public function test_translatable_fields(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $backgroundPage = BackgroundPage::create([
            'slug' => ['de' => 'de-slug', 'en' => 'en-slug'],
            'content' => ['de' => '<p>Deutsch</p>', 'en' => '<p>English</p>'],
            'position' => 0,
            'tile_id' => $tile->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('de-slug', $backgroundPage->getTranslation('slug', 'de'));
        $this->assertEquals('en-slug', $backgroundPage->getTranslation('slug', 'en'));
        $this->assertEquals('<p>Deutsch</p>', $backgroundPage->getTranslation('content', 'de'));
        $this->assertEquals('<p>English</p>', $backgroundPage->getTranslation('content', 'en'));
    }

    public function test_belongs_to_tenant_scope(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);

        $tile = Tile::factory()->forTenant($this->tenant)->create();
        $otherTile = Tile::factory()->forTenant($otherTenant)->create();

        BackgroundPage::create([
            'slug' => ['de' => 'default-page', 'en' => 'default-page'],
            'content' => ['de' => '<p>Default</p>', 'en' => '<p>Default</p>'],
            'position' => 0,
            'tile_id' => $tile->id,
            'tenant_id' => $this->tenant->id,
        ]);

        BackgroundPage::create([
            'slug' => ['de' => 'other-page', 'en' => 'other-page'],
            'content' => ['de' => '<p>Other</p>', 'en' => '<p>Other</p>'],
            'position' => 0,
            'tile_id' => $otherTile->id,
            'tenant_id' => $otherTenant->id,
        ]);

        // The BelongsToTenant global scope should filter by current tenant context.
        // Since in tests the default tenant is resolved, only default tenant's pages should appear.
        $pages = BackgroundPage::all();

        $this->assertTrue($pages->contains(fn ($p) => $p->getTranslation('slug', 'de') === 'default-page'));
        $this->assertFalse($pages->contains(fn ($p) => $p->getTranslation('slug', 'de') === 'other-page'));
    }

    public function test_set_translatable_field_updates_correctly(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $backgroundPage = BackgroundPage::create([
            'slug' => ['de' => 'original', 'en' => 'original'],
            'content' => ['de' => '<p>Original</p>', 'en' => '<p>Original</p>'],
            'position' => 0,
            'tile_id' => $tile->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $backgroundPage->setTranslation('slug', 'de', 'aktualisiert');
        $backgroundPage->save();
        $backgroundPage->refresh();

        $this->assertEquals('aktualisiert', $backgroundPage->getTranslation('slug', 'de'));
        $this->assertEquals('original', $backgroundPage->getTranslation('slug', 'en'));
    }
}
