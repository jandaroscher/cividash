<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ManageDataByPeriod;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageDataByPeriodTest extends TestCase
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

    // ========== Render ==========

    public function test_page_renders(): void
    {
        Livewire::test(ManageDataByPeriod::class)
            ->assertSuccessful();
    }

    // ========== Selectors: distinct period_keys ==========

    public function test_period_selector_lists_distinct_period_keys_without_duplicates(): void
    {
        // Two tiles with the same period_key — should appear once in the list
        $tileA = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);
        $tileB = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 2,
        ]);

        TimePeriod::factory()->forTile($tileA)->year(2024)->create();
        TimePeriod::factory()->forTile($tileB)->year(2024)->create();
        TimePeriod::factory()->forTile($tileA)->year(2023)->create();

        $component = Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year');

        // The options should list 2024 and 2023 — each once
        $periodOptions = $component->instance()->getPeriodKeyOptionsPublic();
        $this->assertCount(2, $periodOptions);
        $this->assertArrayHasKey('2024', $periodOptions);
        $this->assertArrayHasKey('2023', $periodOptions);
    }

    // ========== Matrix: tiles + metric definitions shown ==========

    public function test_matrix_shows_tiles_and_metric_definitions_with_prefilled_values(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Testkachel', 'en' => 'Test Tile'],
            'position' => 1,
        ]);

        $def = MetricDefinition::factory()->forTile($tile)->create([
            'label' => ['de' => 'CO2', 'en' => 'CO2'],
            'is_active' => true,
        ]);

        $tp = TimePeriod::factory()->forTile($tile)->year(2024)->create();

        MetricValue::factory()->forDefinition($def)->forTimePeriod($tp)->create([
            'value' => 42.50,
        ]);

        $component = Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024');

        $component->assertSuccessful();

        // The form state should contain the pre-filled value
        $data = $component->get('data');
        $this->assertEquals('42.50', $data["tile_{$tile->id}"]["metric_{$def->id}"]);
    }

    // ========== Search: filter tile matrix ==========

    public function test_search_filters_tiles_by_title(): void
    {
        $energyTile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 1,
        ]);
        MetricDefinition::factory()->forTile($energyTile)->create(['is_active' => true]);

        $mobilityTile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'position' => 2,
        ]);
        MetricDefinition::factory()->forTile($mobilityTile)->create(['is_active' => true]);

        TimePeriod::factory()->forTile($energyTile)->year(2024)->create();
        TimePeriod::factory()->forTile($mobilityTile)->year(2024)->create();

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set('search', 'Energy')
            ->assertSeeText('Energy')
            ->assertDontSeeText('Mobility');
    }

    public function test_search_filters_tiles_by_metric_label(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Testkachel', 'en' => 'Test Tile'],
            'position' => 1,
        ]);

        MetricDefinition::factory()->forTile($tile)->create([
            'label' => ['de' => 'CO2-Ausstoß', 'en' => 'CO2 emissions'],
            'is_active' => true,
        ]);

        $otherTile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Andere Kachel', 'en' => 'Other Tile'],
            'position' => 2,
        ]);
        MetricDefinition::factory()->forTile($otherTile)->create([
            'label' => ['de' => 'Wasserverbrauch', 'en' => 'Water usage'],
            'is_active' => true,
        ]);

        TimePeriod::factory()->forTile($tile)->year(2024)->create();
        TimePeriod::factory()->forTile($otherTile)->year(2024)->create();

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set('search', 'CO2')
            ->assertSeeText('Test Tile')
            ->assertDontSeeText('Other Tile');
    }

    public function test_search_empty_shows_all_tiles(): void
    {
        $tileA = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 1,
        ]);
        MetricDefinition::factory()->forTile($tileA)->create(['is_active' => true]);

        $tileB = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'position' => 2,
        ]);
        MetricDefinition::factory()->forTile($tileB)->create(['is_active' => true]);

        TimePeriod::factory()->forTile($tileA)->year(2024)->create();
        TimePeriod::factory()->forTile($tileB)->year(2024)->create();

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set('search', '')
            ->assertSeeText('Energy')
            ->assertSeeText('Mobility');
    }

    public function test_search_with_no_matches_shows_hint(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 1,
        ]);
        MetricDefinition::factory()->forTile($tile)->create(['is_active' => true]);

        TimePeriod::factory()->forTile($tile)->year(2024)->create();

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set('search', 'Zzzznonexistent')
            ->assertSeeText(__('filament.pages.manage_data_by_period.search_no_results'));
    }

    public function test_save_persists_value_for_tile_hidden_by_active_search(): void
    {
        // Regression guard: dehydratedWhenHidden() must keep a hidden tile's
        // fields in the form state, so an active search never silently drops
        // an in-progress edit on save.
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'position' => 1,
        ]);
        $def = MetricDefinition::factory()->forTile($tile)->create(['is_active' => true]);

        TimePeriod::factory()->forTile($tile)->year(2024)->create();

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set("data.tile_{$tile->id}.metric_{$def->id}", '77.00')
            // Search term matches nothing — tile's Section is hidden at save time
            ->set('search', 'Zzzznonexistent')
            ->call('save');

        $this->assertDatabaseHas('metric_values', [
            'metric_definition_id' => $def->id,
            'value' => 77.00,
        ]);
    }

    // ========== Save: update existing value ==========

    public function test_save_updates_existing_metric_value(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);

        $def = MetricDefinition::factory()->forTile($tile)->create([
            'is_active' => true,
        ]);

        $tp = TimePeriod::factory()->forTile($tile)->year(2024)->create();
        $mv = MetricValue::factory()->forDefinition($def)->forTimePeriod($tp)->create([
            'value' => 10.00,
        ]);

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set("data.tile_{$tile->id}.metric_{$def->id}", '99.00')
            ->call('save');

        $this->assertDatabaseHas('metric_values', [
            'id' => $mv->id,
            'value' => 99.00,
        ]);
    }

    // ========== Save: new value + missing TimePeriod ==========

    public function test_save_creates_metric_value_and_missing_time_period(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);

        $def = MetricDefinition::factory()->forTile($tile)->create([
            'is_active' => true,
        ]);

        // No TimePeriod exists yet for 2025
        $this->assertDatabaseMissing('time_periods', [
            'tile_id' => $tile->id,
            'period_key' => '2025',
        ]);

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2025')
            ->set("data.tile_{$tile->id}.metric_{$def->id}", '55.00')
            ->call('save');

        $this->assertDatabaseHas('time_periods', [
            'tile_id' => $tile->id,
            'period_key' => '2025',
        ]);

        $tp = TimePeriod::where('tile_id', $tile->id)->where('period_key', '2025')->first();

        $this->assertDatabaseHas('metric_values', [
            'metric_definition_id' => $def->id,
            'time_period_id' => $tp->id,
            'value' => 55.00,
        ]);
    }

    // ========== Save: empty field = skip (no create, no delete) ==========

    public function test_save_skips_empty_fields_without_deleting(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);

        $def = MetricDefinition::factory()->forTile($tile)->create([
            'is_active' => true,
        ]);

        $tp = TimePeriod::factory()->forTile($tile)->year(2024)->create();
        $mv = MetricValue::factory()->forDefinition($def)->forTimePeriod($tp)->create([
            'value' => 77.00,
        ]);

        // Submit with empty value
        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set("data.tile_{$tile->id}.metric_{$def->id}", '')
            ->call('save');

        // Existing value must still be there (skip, not delete)
        $this->assertDatabaseHas('metric_values', [
            'id' => $mv->id,
            'value' => 77.00,
        ]);
    }

    public function test_save_does_not_create_metric_value_for_empty_new_field(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);

        $def = MetricDefinition::factory()->forTile($tile)->create([
            'is_active' => true,
        ]);

        // No TimePeriod, empty input => nothing should be created
        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2025')
            ->set("data.tile_{$tile->id}.metric_{$def->id}", '')
            ->call('save');

        $this->assertDatabaseMissing('metric_values', [
            'metric_definition_id' => $def->id,
        ]);

        $this->assertDatabaseMissing('time_periods', [
            'tile_id' => $tile->id,
            'period_key' => '2025',
        ]);
    }

    // ========== Save: server-side validation ==========

    public function test_save_rejects_non_numeric_value_with_form_error(): void
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);

        $def = MetricDefinition::factory()->forTile($tile)->create([
            'is_active' => true,
        ]);

        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2025')
            ->set("data.tile_{$tile->id}.metric_{$def->id}", 'not-a-number')
            ->call('save')
            ->assertHasFormErrors(["tile_{$tile->id}.metric_{$def->id}"]);

        $this->assertDatabaseMissing('metric_values', [
            'metric_definition_id' => $def->id,
        ]);

        $this->assertDatabaseMissing('time_periods', [
            'tile_id' => $tile->id,
            'period_key' => '2025',
        ]);
    }

    // ========== Tenant isolation ==========

    public function test_tenant_isolation_hides_other_tenant_tiles(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);

        $myTile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Meine Kachel', 'en' => 'My Tile'],
            'position' => 1,
        ]);
        $def = MetricDefinition::factory()->forTile($myTile)->create(['is_active' => true]);
        $tp = TimePeriod::factory()->forTile($myTile)->year(2024)->create();
        MetricValue::factory()->forDefinition($def)->forTimePeriod($tp)->create(['value' => 11.0]);

        $foreignTile = Tile::factory()->create([
            'tenant_id' => $otherTenant->id,
            'time_granularity' => 'year',
            'title' => ['de' => 'Fremde Kachel', 'en' => 'Foreign Tile'],
            'position' => 2,
        ]);
        $foreignDef = MetricDefinition::factory()->create([
            'tile_id' => $foreignTile->id,
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        $foreignTp = TimePeriod::factory()->forTile($foreignTile)->year(2024)->create();
        MetricValue::factory()->create([
            'metric_definition_id' => $foreignDef->id,
            'time_period_id' => $foreignTp->id,
            'value' => 999.0,
            'tenant_id' => $otherTenant->id,
        ]);

        $data = Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->get('data');

        // Own tile key must be present
        $this->assertArrayHasKey("tile_{$myTile->id}", $data);

        // Foreign tile key must NOT appear
        $this->assertArrayNotHasKey("tile_{$foreignTile->id}", $data);
    }

    public function test_tenant_isolation_cannot_write_to_foreign_tile(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant 2', 'slug' => 'other-tenant-2']);

        $foreignTile = Tile::factory()->create([
            'tenant_id' => $otherTenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);
        $foreignDef = MetricDefinition::factory()->create([
            'tile_id' => $foreignTile->id,
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        // Try to inject foreign tile data via set
        Livewire::test(ManageDataByPeriod::class)
            ->set('selectedGranularity', 'year')
            ->set('selectedPeriodKey', '2024')
            ->set("data.tile_{$foreignTile->id}.metric_{$foreignDef->id}", '123.00')
            ->call('save');

        // No MetricValue for foreign definition must exist
        $this->assertDatabaseMissing('metric_values', [
            'metric_definition_id' => $foreignDef->id,
        ]);
    }

    // ========== Multiple granularities ==========

    public function test_tiles_filtered_by_granularity(): void
    {
        $yearTile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'year',
            'position' => 1,
        ]);
        $quarterTile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'time_granularity' => 'quarter',
            'position' => 2,
        ]);

        MetricDefinition::factory()->forTile($yearTile)->create(['is_active' => true]);
        MetricDefinition::factory()->forTile($quarterTile)->create(['is_active' => true]);

        TimePeriod::factory()->forTile($yearTile)->year(2024)->create();
        TimePeriod::factory()->forTile($quarterTile)->quarter(2024, 1)->create();

        // Year granularity: only year tile in state (year is the default, so mount() already sets it up)
        $yearComponent = Livewire::test(ManageDataByPeriod::class);
        $yearComponent->set('selectedGranularity', 'year');
        $yearComponent->set('selectedPeriodKey', '2024');
        $yearComponent->call('save'); // triggers fillFormFromDatabase
        $yearData = $yearComponent->get('data');

        $this->assertArrayHasKey("tile_{$yearTile->id}", $yearData);
        $this->assertArrayNotHasKey("tile_{$quarterTile->id}", $yearData);

        // Quarter granularity: only quarter tile in state
        $quarterComponent = Livewire::test(ManageDataByPeriod::class);
        $quarterComponent->set('selectedGranularity', 'quarter');
        $quarterComponent->set('selectedPeriodKey', '2024-Q1');
        $quarterComponent->call('save'); // triggers fillFormFromDatabase
        $quarterData = $quarterComponent->get('data');

        $this->assertArrayNotHasKey("tile_{$yearTile->id}", $quarterData);
        $this->assertArrayHasKey("tile_{$quarterTile->id}", $quarterData);
    }
}
