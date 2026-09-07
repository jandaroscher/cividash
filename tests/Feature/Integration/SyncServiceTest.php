<?php

namespace Tests\Feature\Integration;

use App\Contracts\Integration\DataMapperInterface;
use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Enums\TimeGranularity;
use App\Models\Category;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use App\Services\Integration\NgsiLdDataMapper;
use App\Services\Integration\SyncService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::where('slug', 'default')->first();
        $user->tenants()->sync([$this->tenant->id]);
        Filament::auth()->login($user);
        Filament::setTenant($this->tenant);

        // Bind the real NGSI-LD mapper for production-faithful mapping behaviour.
        $this->app->instance(DataMapperInterface::class, new NgsiLdDataMapper);
    }

    /**
     * A canonical NGSI-LD Indicator entity used across tests.
     */
    private function indicator(string $id, string $deName, array $values, ?string $category = null, ?string $granularity = null): array
    {
        $entity = [
            'id' => $id,
            'type' => 'NachhaltigkeitsIndikator',
            'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => $deName, 'en' => $deName]],
            'unit' => ['type' => 'Property', 'value' => 't'],
            'dataPoints' => ['type' => 'Property', 'value' => $values],
        ];

        if ($granularity !== null) {
            $entity['timeGranularity'] = ['type' => 'Property', 'value' => $granularity];
        }

        if ($category !== null) {
            $entity['category'] = ['type' => 'Relationship', 'object' => $category];
        }

        return $entity;
    }

    /**
     * Bind a fake external data source returning the given entities in a single page.
     */
    private function fakeSource(array $entities): ExternalDataSourceInterface
    {
        $fake = new class($entities) implements ExternalDataSourceInterface
        {
            public function __construct(private array $entities) {}

            public function isConnected(): bool
            {
                return true;
            }

            public function fetchEntities(string $type, array $filters = [], int $limit = 100, int $offset = 0): array
            {
                $page = array_slice($this->entities, $offset, $limit);

                return ['entities' => $page, 'total' => count($this->entities)];
            }

            public function fetchEntity(string $id): ?array
            {
                foreach ($this->entities as $entity) {
                    if (($entity['id'] ?? null) === $id) {
                        return $entity;
                    }
                }

                return null;
            }

            public function getAvailableEntityTypes(): array
            {
                return ['NachhaltigkeitsIndikator'];
            }
        };

        $this->app->instance(ExternalDataSourceInterface::class, $fake);

        return $fake;
    }

    private function service(ExternalDataSourceInterface $source): SyncService
    {
        return new SyncService($source, $this->app->make(DataMapperInterface::class));
    }

    public function test_creates_tile_metric_definition_time_periods_and_metric_values_for_new_indicator(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [
                ['period' => '2022', 'value' => 12.34],
                ['period' => '2023', 'value' => null],
            ]),
        ]);

        $result = $this->service($source)->syncAll($this->tenant);

        $this->assertSame(1, $result->created);
        $this->assertFalse($result->dryRun);

        $tile = Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:co2')->first();
        $this->assertNotNull($tile);
        $this->assertSame('CO2', $tile->getTranslation('title', 'de'));
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tile->external_source);
        $this->assertSame('year', $this->granularityValue($tile->time_granularity));
        $this->assertNotNull($tile->source_hash);
        $this->assertNotNull($tile->last_synced_at);

        $definition = MetricDefinition::withoutGlobalScopes()->where('tile_id', $tile->id)->first();
        $this->assertNotNull($definition);
        $this->assertSame('co2', $definition->metric_key);

        $this->assertSame(2, TimePeriod::withoutGlobalScopes()->where('tile_id', $tile->id)->count());
        $this->assertSame(2, MetricValue::withoutGlobalScopes()->where('metric_definition_id', $definition->id)->count());

        $period2022 = TimePeriod::withoutGlobalScopes()->where('tile_id', $tile->id)->where('period_key', '2022')->first();
        $this->assertNotNull($period2022);
        $value2022 = MetricValue::withoutGlobalScopes()
            ->where('metric_definition_id', $definition->id)
            ->where('time_period_id', $period2022->id)
            ->first();
        $this->assertSame('12.34', (string) $value2022->value);

        $period2023 = TimePeriod::withoutGlobalScopes()->where('tile_id', $tile->id)->where('period_key', '2023')->first();
        $value2023 = MetricValue::withoutGlobalScopes()
            ->where('metric_definition_id', $definition->id)
            ->where('time_period_id', $period2023->id)
            ->first();
        $this->assertNull($value2023->value);
    }

    public function test_tolerates_legacy_year_data_points(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:legacy', 'Legacy', [
                ['year' => 2021, 'value' => 5.0],
            ]),
        ]);

        $this->service($source)->syncAll($this->tenant);

        $tile = Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:legacy')->first();
        $period = TimePeriod::withoutGlobalScopes()->where('tile_id', $tile->id)->first();

        $this->assertNotNull($period);
        $this->assertSame('2021', $period->period_key);
        $this->assertSame('year', $this->granularityValue($period->granularity));
    }

    public function test_quarter_granularity_creates_quarter_time_periods(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:pm10', 'PM10', [
                ['period' => '2024-Q1', 'value' => 18.4],
                ['period' => '2024-Q2', 'value' => 12.1],
            ], null, 'quarter'),
        ]);

        $this->service($source)->syncAll($this->tenant);

        $tile = Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:pm10')->first();
        $this->assertSame('quarter', $this->granularityValue($tile->time_granularity));

        $this->assertSame(2, TimePeriod::withoutGlobalScopes()->where('tile_id', $tile->id)->count());

        $q1 = TimePeriod::withoutGlobalScopes()->where('tile_id', $tile->id)->where('period_key', '2024-Q1')->first();
        $this->assertNotNull($q1);
        $this->assertSame('quarter', $this->granularityValue($q1->granularity));
        $this->assertSame('Q1 2024', $q1->label);
    }

    /**
     * Normalize a granularity value that may be a string or a TimeGranularity enum.
     */
    private function granularityValue(mixed $granularity): string
    {
        return $granularity instanceof TimeGranularity ? $granularity->value : (string) $granularity;
    }

    public function test_links_category_via_belongs_to_many(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [
                ['year' => 2022, 'value' => 1.0],
            ], 'urn:ngsi-ld:Category:mobility'),
        ]);

        $this->service($source)->syncAll($this->tenant);

        $tile = Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:co2')->first();
        $category = Category::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Category:mobility')->first();

        $this->assertNotNull($category);
        $this->assertSame('mobility', $category->key);
        $this->assertTrue($tile->categories()->where('categories.id', $category->id)->exists());
    }

    public function test_second_run_with_identical_payload_is_skipped(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [
                ['year' => 2022, 'value' => 1.0],
            ]),
        ]);

        $first = $this->service($source)->syncAll($this->tenant);
        $this->assertSame(1, $first->created);

        $tileCountAfterFirst = Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();
        $valueCountAfterFirst = MetricValue::withoutGlobalScopes()->count();

        $second = $this->service($source)->syncAll($this->tenant);

        $this->assertSame(0, $second->created);
        $this->assertSame(0, $second->updated);
        $this->assertSame(1, $second->skipped);

        $this->assertSame($tileCountAfterFirst, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame($valueCountAfterFirst, MetricValue::withoutGlobalScopes()->count());
    }

    public function test_changed_value_updates_without_duplicating_rows(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [
                ['year' => 2022, 'value' => 1.0],
            ]),
        ]);

        $this->service($source)->syncAll($this->tenant);

        // Same id, changed value => different source_hash.
        $changedSource = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [
                ['year' => 2022, 'value' => 9.99],
            ]),
        ]);

        $result = $this->service($changedSource)->syncAll($this->tenant);

        $this->assertSame(1, $result->updated);
        $this->assertSame(0, $result->created);

        $tile = Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:co2')->first();
        $definition = MetricDefinition::withoutGlobalScopes()->where('tile_id', $tile->id)->first();

        $this->assertSame(1, Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:co2')->count());
        $this->assertSame(1, MetricValue::withoutGlobalScopes()->where('metric_definition_id', $definition->id)->count());

        $value = MetricValue::withoutGlobalScopes()->where('metric_definition_id', $definition->id)->first();
        $this->assertSame('9.99', (string) $value->value);
    }

    public function test_dry_run_persists_nothing_but_reports_created(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [
                ['year' => 2022, 'value' => 1.0],
            ]),
        ]);

        $result = $this->service($source)->syncAll($this->tenant, force: false, dryRun: true);

        $this->assertSame(1, $result->created);
        $this->assertTrue($result->dryRun);

        $this->assertSame(0, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
        $this->assertSame(0, MetricValue::withoutGlobalScopes()->count());
    }

    public function test_bad_entity_does_not_abort_the_run(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:good-1', 'Good 1', [['year' => 2022, 'value' => 1.0]]),
            ['type' => 'NachhaltigkeitsIndikator', 'name' => ['type' => 'LanguageProperty', 'languageMap' => ['de' => 'No Id']]],
            $this->indicator('urn:ngsi-ld:Indicator:good-2', 'Good 2', [['year' => 2022, 'value' => 2.0]]),
        ]);

        $result = $this->service($source)->syncAll($this->tenant);

        $this->assertSame(2, $result->created);
        $this->assertSame(1, $result->failed);
        $this->assertNotEmpty($result->errors);

        $this->assertSame(2, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
    }

    public function test_multi_tenant_isolation(): void
    {
        $secondTenant = Tenant::create(['name' => 'Second', 'slug' => 'second']);

        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [['year' => 2022, 'value' => 1.0]]),
        ]);

        $this->service($source)->syncAll($this->tenant);
        $this->service($source)->syncAll($secondTenant);

        $this->assertSame(1, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
        $this->assertSame(1, Tile::withoutGlobalScopes()->where('tenant_id', $secondTenant->id)->whereNotNull('external_source')->count());

        // Each tile is bound to its own tenant.
        $tile1 = Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('external_id', 'urn:ngsi-ld:Indicator:co2')->first();
        $tile2 = Tile::withoutGlobalScopes()->where('tenant_id', $secondTenant->id)->where('external_id', 'urn:ngsi-ld:Indicator:co2')->first();

        $this->assertNotNull($tile1);
        $this->assertNotNull($tile2);
        $this->assertNotSame($tile1->id, $tile2->id);
    }

    public function test_sync_succeeds_without_authenticated_user_console_context(): void
    {
        // Simulate the scheduled/console run: NO Filament user is logged in.
        // Regression guard for the TenantSet-requires-a-user crash that the
        // logged-in setUp() masked.
        Filament::auth()->logout();
        Filament::setTenant(null, isQuiet: true);

        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [['year' => 2022, 'value' => 1.0]]),
        ]);

        $result = $this->service($source)->syncAll($this->tenant);

        $this->assertSame(1, $result->created);
        $this->assertSame(1, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
    }

    public function test_pagination_loops_until_results_count_reached(): void
    {
        config(['integrations.civitas.sync.batch_size' => 2]);

        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:a', 'A', [['year' => 2022, 'value' => 1.0]]),
            $this->indicator('urn:ngsi-ld:Indicator:b', 'B', [['year' => 2022, 'value' => 2.0]]),
            $this->indicator('urn:ngsi-ld:Indicator:c', 'C', [['year' => 2022, 'value' => 3.0]]),
        ]);

        $result = $this->service($source)->syncAll($this->tenant);

        $this->assertSame(3, $result->created);
        $this->assertSame(3, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
    }

    public function test_prune_removed_deletes_stale_tiles_when_enabled(): void
    {
        config(['integrations.civitas.sync.prune_removed' => true]);

        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:a', 'A', [['year' => 2022, 'value' => 1.0]]),
            $this->indicator('urn:ngsi-ld:Indicator:b', 'B', [['year' => 2022, 'value' => 2.0]]),
        ]);

        $this->service($source)->syncAll($this->tenant);
        $this->assertSame(2, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());

        // Second run only returns 'a' => 'b' should be pruned.
        $smallerSource = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:a', 'A', [['year' => 2022, 'value' => 1.0]]),
        ]);

        $result = $this->service($smallerSource)->syncAll($this->tenant);

        $this->assertSame(1, $result->deleted);
        $this->assertSame(1, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
        $this->assertNull(Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:b')->first());
        $this->assertNotNull(Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:a')->first());
    }

    public function test_prune_removed_does_not_delete_when_disabled(): void
    {
        config(['integrations.civitas.sync.prune_removed' => false]);

        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:a', 'A', [['year' => 2022, 'value' => 1.0]]),
            $this->indicator('urn:ngsi-ld:Indicator:b', 'B', [['year' => 2022, 'value' => 2.0]]),
        ]);

        $this->service($source)->syncAll($this->tenant);

        $smallerSource = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:a', 'A', [['year' => 2022, 'value' => 1.0]]),
        ]);

        $result = $this->service($smallerSource)->syncAll($this->tenant);

        $this->assertSame(0, $result->deleted);
        $this->assertSame(2, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
    }

    public function test_prune_does_not_delete_when_source_returns_no_entities(): void
    {
        config(['integrations.civitas.sync.prune_removed' => true]);

        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:a', 'A', [['year' => 2022, 'value' => 1.0]]),
        ]);
        $this->service($source)->syncAll($this->tenant);
        $this->assertSame(1, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());

        // Source now returns nothing (e.g. transient/auth error). Must NOT wipe existing tiles.
        $emptySource = $this->fakeSource([]);
        $result = $this->service($emptySource)->syncAll($this->tenant);

        $this->assertSame(0, $result->deleted);
        $this->assertSame(1, Tile::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNotNull('external_source')->count());
        $this->assertNotNull(Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:a')->first());
    }

    public function test_force_reimports_even_when_hash_matches(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [['year' => 2022, 'value' => 1.0]]),
        ]);

        $this->service($source)->syncAll($this->tenant);

        $result = $this->service($source)->syncAll($this->tenant, force: true);

        $this->assertSame(0, $result->skipped);
        $this->assertSame(1, $result->updated);
    }

    public function test_sync_entity_creates_single_record(): void
    {
        $source = $this->fakeSource([
            $this->indicator('urn:ngsi-ld:Indicator:co2', 'CO2', [['year' => 2022, 'value' => 1.0]]),
        ]);

        $result = $this->service($source)->syncEntity($this->tenant, 'urn:ngsi-ld:Indicator:co2');

        $this->assertSame(1, $result->created);
        $this->assertSame(1, Tile::withoutGlobalScopes()->where('external_id', 'urn:ngsi-ld:Indicator:co2')->count());
    }

    public function test_sync_entity_reports_failure_when_not_found(): void
    {
        $source = $this->fakeSource([]);

        $result = $this->service($source)->syncEntity($this->tenant, 'urn:ngsi-ld:Indicator:missing');

        $this->assertSame(1, $result->failed);
        $this->assertNotEmpty($result->errors);
    }
}
