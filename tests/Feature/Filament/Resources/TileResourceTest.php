<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\TileResource\Pages\CreateTile;
use App\Filament\Resources\TileResource\Pages\EditTile;
use App\Filament\Resources\TileResource\Pages\ListTiles;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TileResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);

        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    // ========== List Page ==========

    public function test_list_page_renders(): void
    {
        Livewire::test(ListTiles::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_tiles(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testkachel', 'en' => 'Test Tile'],
        ]);

        Livewire::test(ListTiles::class)
            ->assertCanSeeTableRecords([$tile]);
    }

    public function test_list_page_search_works(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Einzigartig', 'en' => 'Unique'],
        ]);

        $otherTile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Andere Kachel', 'en' => 'Other Tile'],
        ]);

        Livewire::test(ListTiles::class)
            ->searchTable('Einzigartig')
            ->assertCanSeeTableRecords([$tile])
            ->assertCanNotSeeTableRecords([$otherTile]);
    }

    // ========== Create Page ==========

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateTile::class)
            ->assertSuccessful();
    }

    public function test_create_tile_title_is_required(): void
    {
        Livewire::test(CreateTile::class)
            ->fillForm([
                'title' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_create_tile_form_accepts_valid_data(): void
    {
        $component = Livewire::test(CreateTile::class)
            ->fillForm([
                'title' => 'Neue Kachel',
                'slug' => 'neue-kachel',
                'description' => '<p>Beschreibung</p>',
                'is_public' => true,
            ]);

        // Verify the form state was set correctly
        $component->assertFormSet([
            'title' => 'Neue Kachel',
            'is_public' => true,
        ]);
    }

    public function test_create_tile_completes_successfully(): void
    {
        Livewire::test(CreateTile::class)
            ->fillForm([
                'title' => 'Erstellte Kachel',
                'slug' => 'erstellte-kachel',
                'description' => '<p>Beschreibung</p>',
                'is_public' => true,
                'metricDefinitions' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tile = Tile::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($tile);
        $this->assertEquals('erstellte-kachel', $tile->getTranslation('slug', 'de'));
    }

    public function test_create_tile_is_public_defaults_to_true(): void
    {
        Livewire::test(CreateTile::class)
            ->assertFormSet([
                'is_public' => true,
            ]);
    }

    // ========== Edit Page ==========

    public function test_edit_page_renders(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_edit_page_loads_existing_data(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Originaltitel', 'en' => 'Original Title'],
            'is_public' => true,
        ]);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->assertFormSet([
                'title' => 'Originaltitel',
                'is_public' => true,
            ]);
    }

    public function test_edit_page_saves_changes(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Alt', 'en' => 'Old'],
        ]);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->fillForm([
                'title' => 'Aktualisiert',
                'is_public' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tile->refresh();
        $this->assertEquals('Aktualisiert', $tile->getTranslation('title', 'de'));
        $this->assertFalse($tile->is_public);
    }

    public function test_edit_page_title_is_required(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->fillForm([
                'title' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_edit_page_preserves_is_public_flag(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->private()->create([
            'title' => ['de' => 'Privat', 'en' => 'Private'],
        ]);

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->assertFormSet([
                'is_public' => false,
            ]);
    }

    // ========== Metrics Validation ==========

    public function test_duplicate_year_in_metric_values_shows_validation_error(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->fillForm([
                'metricDefinitions' => [
                    [
                        'metric_key' => 'test-metric',
                        'label' => 'Test Metric',
                        'unit' => '%',
                        'indicator_type' => 'small',
                        'is_active' => true,
                        'metricValues' => [
                            ['tile_year_id' => 2024, 'value' => 100, 'is_active' => true],
                            ['tile_year_id' => 2024, 'value' => 200, 'is_active' => true],
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['metricDefinitions.0.metricValues.0.tile_year_id'])
            ->assertHasFormErrors(['metricDefinitions.0.metricValues.1.tile_year_id']);
    }

    // ========== Auto-Position ==========

    public function test_new_tile_gets_auto_incremented_position(): void
    {
        Tile::factory()->forTenant($this->tenant)->create(['position' => 3]);
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
        Tile::factory()->forTenant($otherTenant)->create(['position' => 99]);

        $tile = Tile::factory()->forTenant($this->tenant)->create(['position' => null]);

        $this->assertEquals(4, $tile->position);
    }

    public function test_tile_with_explicit_position_keeps_it(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create(['position' => 10]);

        $this->assertEquals(10, $tile->position);
    }

    public function test_tile_with_explicit_position_zero_keeps_it(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create(['position' => 0]);

        $this->assertEquals(0, $tile->position);
    }

    // ========== Delete ==========

    public function test_can_delete_tile_from_list(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        Livewire::test(ListTiles::class)
            ->callTableAction('delete', $tile);

        $this->assertDatabaseMissing('tiles', ['id' => $tile->id]);
    }
}
