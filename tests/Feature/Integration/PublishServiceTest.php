<?php

namespace Tests\Feature\Integration;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\Integration\NgsiLdDataMapper;
use App\Services\Integration\PublishResult;
use App\Services\Integration\PublishService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        Filament::setTenant($this->tenant, isQuiet: true);

        config([
            'integrations.civitas.context_url' => null,
            'integrations.civitas.driver' => 'ngsi-ld',
            'integrations.civitas.sync.retry_attempts' => 1,
            'integrations.civitas.sync.retry_delay_seconds' => 0,
        ]);

        // The publish client is built via NgsiLdClient::fromConfig(), which
        // prefers persisted IntegrationSettings over config. Configure the
        // broker through settings, mirroring how an admin sets it up.
        $settings = app(\App\Settings\IntegrationSettings::class);
        $settings->api_url = 'https://broker.example.com/context/ngsi-ld';
        $settings->oauth_token_url = 'https://keycloak.example.com/token';
        $settings->oauth_client_id = 'dashboard';
        $settings->oauth_client_secret = 'secret';
        $settings->save();
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    /**
     * Create a publishable Tile with one metric definition + two data points.
     */
    private function makeTileWithMetrics(array $attributes = []): Tile
    {
        $tile = Tile::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'],
            'description' => ['de' => 'Beschreibung', 'en' => 'Description'],
            'slug' => ['de' => 'co2-emissionen', 'en' => 'co2-emissions'],
            'time_granularity' => 'year',
        ], $attributes));

        $definition = MetricDefinition::factory()->forTile($tile)->create([
            'metric_key' => 'co2',
            'unit' => ['de' => 't', 'en' => 't'],
        ]);

        foreach (['2023' => 1.5, '2024' => 2.5] as $period => $value) {
            $timePeriod = TimePeriod::factory()->forTile($tile)->create(['period_key' => $period]);
            MetricValue::factory()
                ->forDefinition($definition)
                ->forTimePeriod($timePeriod)
                ->create(['value' => $value]);
        }

        return $tile->fresh();
    }

    private function service(): PublishService
    {
        return app(PublishService::class);
    }

    public function test_publishes_tile_and_stamps_provenance(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tile = $this->makeTileWithMetrics();

        $result = $this->service()->publishTile($tile);

        $this->assertInstanceOf(PublishResult::class, $result);
        $this->assertTrue($result->published);

        // The broker received a NachhaltigkeitsIndikator with dataPoints (not values).
        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/entities')) {
                return false;
            }

            return $request['type'] === 'NachhaltigkeitsIndikator'
                && isset($request['dataPoints'])
                && ! isset($request['values'])
                && $request['name']['languageMap']['de'] === 'CO2-Emissionen';
        });

        $tile->refresh();
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tile->external_source);
        $this->assertSame('urn:ngsi-ld:NachhaltigkeitsIndikator:co2-emissionen', $tile->external_id);
        $this->assertNotNull($tile->source_hash);
        $this->assertNotNull($tile->last_synced_at);
    }

    public function test_republishing_unchanged_content_is_idempotent_noop(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tile = $this->makeTileWithMetrics();

        $this->service()->publishTile($tile);

        // Second publish of identical content must NOT write to the broker again.
        $result = $this->service()->publishTile($tile->fresh());

        $this->assertFalse($result->published);
        $this->assertTrue($result->skipped);

        $entityPosts = 0;
        Http::recorded(function (Request $request) use (&$entityPosts) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/entities')) {
                $entityPosts++;
            }

            return false;
        });

        $this->assertSame(1, $entityPosts, 'Unchanged re-publish must be an idempotent no-op.');
    }

    public function test_forced_republish_writes_even_when_unchanged(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tile = $this->makeTileWithMetrics();
        $this->service()->publishTile($tile);

        $result = $this->service()->publishTile($tile->fresh(), force: true);

        $this->assertTrue($result->published);

        $entityWrites = 0;
        Http::recorded(function (Request $request) use (&$entityWrites) {
            if (str_contains($request->url(), '/entities') && in_array($request->method(), ['POST', 'PATCH'], true)) {
                $entityWrites++;
            }

            return false;
        });

        $this->assertSame(2, $entityWrites, 'Forced re-publish must write to the broker again.');
    }

    public function test_refuses_tile_with_foreign_provenance(): void
    {
        Http::fake();

        $tile = $this->makeTileWithMetrics();
        $tile->forceFill(['external_source' => 'some-other-system', 'external_id' => 'urn:foreign:1'])->save();

        $this->expectException(\App\Exceptions\Integration\ForeignProvenanceException::class);

        try {
            $this->service()->publishTile($tile->fresh());
        } finally {
            // No broker write may have happened.
            Http::assertNothingSent();
        }
    }

    public function test_publishes_tile_previously_synced_from_civitas_core(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities/*/attrs' => Http::response('', 204),
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => 'exists'], 409),
        ]);

        $tile = $this->makeTileWithMetrics();
        $tile->forceFill([
            'external_source' => NgsiLdDataMapper::SOURCE_KEY,
            'external_id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2-core',
        ])->save();

        $result = $this->service()->publishTile($tile->fresh());

        $this->assertTrue($result->published);

        // Round-trip: the broker entity id reuses the stored external_id and the
        // upsert fell back to PATCH /attrs because the entity already exists.
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_contains($request->url(), 'co2-core/attrs'));
    }

    public function test_forced_republish_over_existing_entity_patches_attrs(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities/*/attrs' => Http::response('', 204),
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => 'exists'], 409),
        ]);

        $tile = $this->makeTileWithMetrics();
        $tile->forceFill([
            'external_source' => NgsiLdDataMapper::SOURCE_KEY,
            'external_id' => 'urn:ngsi-ld:NachhaltigkeitsIndikator:co2-core',
        ])->save();

        // Pre-stamp the current hash so an unforced publish would be skipped —
        // proving force pushes through the POST->409->PATCH /attrs path anyway.
        $entity = app(NgsiLdDataMapper::class)->mapTileToEntity($tile->fresh());
        $tile->forceFill(['source_hash' => app(NgsiLdDataMapper::class)->computeSourceHash($entity)])->save();

        $result = $this->service()->publishTile($tile->fresh(), force: true);

        $this->assertTrue($result->published);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/entities'));
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && str_contains($request->url(), 'co2-core/attrs'));
    }

    public function test_publishing_one_tenants_tile_does_not_touch_another_tenants_tile(): void
    {
        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        // Tenant A is the active tenant ($this->tenant). Build a second tenant B
        // with its own tile that must stay untouched.
        $tileA = $this->makeTileWithMetrics();

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        $tileB = Tile::factory()->create([
            'tenant_id' => $tenantB->id,
            'title' => ['de' => 'B-Indikator', 'en' => 'B Indicator'],
            'slug' => ['de' => 'b-indikator', 'en' => 'b-indicator'],
            'time_granularity' => 'year',
        ]);

        $this->service()->publishTile($tileA);

        $tileA->refresh();
        $tileB->refresh();

        // Only tenant A's tile is stamped; tenant B's tile is left completely alone.
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tileA->external_source);
        $this->assertSame($this->tenant->id, $tileA->tenant_id);

        $this->assertNull($tileB->external_source);
        $this->assertNull($tileB->external_id);
        $this->assertNull($tileB->source_hash);
        $this->assertNull($tileB->last_synced_at);
        $this->assertSame($tenantB->id, $tileB->tenant_id);
    }

    public function test_refuses_tile_without_tenant(): void
    {
        Http::fake();

        $tile = $this->makeTileWithMetrics();
        // Detach the tenant in memory to simulate a tenantless tile.
        $tile->tenant_id = null;

        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->service()->publishTile($tile);
        } finally {
            Http::assertNothingSent();
        }
    }
}
