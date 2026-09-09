<?php

namespace Tests\Feature\Filament\Resources;

use App\Contracts\Integration\WritableDataSourceInterface;
use App\Filament\Resources\TileResource;
use App\Filament\Resources\TileResource\Pages\EditTile;
use App\Filament\Resources\TileResource\Pages\ListTiles;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use App\Services\Integration\NgsiLdDataMapper;
use App\Settings\IntegrationSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class PublishTileActionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        Filament::setTenant($this->tenant);

        config([
            'integrations.civitas.enabled' => true,
            'integrations.civitas.driver' => 'ngsi-ld',
            'integrations.civitas.context_url' => null,
            'integrations.civitas.sync.retry_attempts' => 1,
            'integrations.civitas.sync.retry_delay_seconds' => 0,
        ]);

        $settings = app(IntegrationSettings::class);
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

    private function makePublishableTile(array $attributes = []): Tile
    {
        $tile = Tile::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'],
            'slug' => ['de' => 'co2-emissionen', 'en' => 'co2-emissions'],
            'time_granularity' => 'year',
        ], $attributes));

        $definition = MetricDefinition::factory()->forTile($tile)->create(['metric_key' => 'co2', 'unit' => ['de' => 't', 'en' => 't']]);
        $timePeriod = TimePeriod::factory()->forTile($tile)->create(['period_key' => '2023']);
        MetricValue::factory()->forDefinition($definition)->forTimePeriod($timePeriod)->create(['value' => 1.5]);

        return $tile->fresh();
    }

    public function test_edit_page_publish_action_writes_to_core_de(): void
    {
        app()->setLocale('de');

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tile = $this->makePublishableTile();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->callAction('publishToCore')
            ->assertNotified(__('filament.resources.tile.actions.publish_success'));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/entities')
            && $request['type'] === 'NachhaltigkeitsIndikator'
            && isset($request['dataPoints'])
            && ! isset($request['values']));

        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tile->fresh()->external_source);
    }

    public function test_edit_page_publish_action_writes_to_core_en(): void
    {
        app()->setLocale('en');

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tile = $this->makePublishableTile();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->callAction('publishToCore')
            ->assertNotified(__('filament.resources.tile.actions.publish_success'));

        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tile->fresh()->external_source);
    }

    public function test_list_page_row_publish_action_writes_to_core(): void
    {
        app()->setLocale('de');

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tile = $this->makePublishableTile();

        Livewire::test(ListTiles::class)
            ->callTableAction('publishToCore', $tile)
            ->assertNotified(__('filament.resources.tile.actions.publish_success'));

        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tile->fresh()->external_source);
    }

    public function test_publish_action_refuses_foreign_provenance_tile(): void
    {
        app()->setLocale('de');

        Http::fake();

        $tile = $this->makePublishableTile();
        $tile->forceFill(['external_source' => 'some-other-system', 'external_id' => 'urn:foreign:1'])->save();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->callAction('publishToCore')
            ->assertNotified(__('filament.resources.tile.actions.publish_foreign_provenance'));

        Http::assertNothingSent();
    }

    public function test_non_admin_user_cannot_invoke_publish_action(): void
    {
        // visible() is render-only; the server-side authorize() gate must also
        // deny a non-admin. For a non-admin the action is both hidden AND not
        // authorized, so a direct Livewire invocation is refused.
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $this->actingAs($nonAdmin);

        Http::fake();

        $tile = $this->makePublishableTile();

        // The action is not rendered for a non-admin...
        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->assertActionHidden('publishToCore');

        // ...and the server-side authorization gate itself denies the non-admin
        // (this is what authorize() consults, blocking direct invocation).
        $this->assertFalse(TileResource::canUserPublishToCore());

        // An admin in the same context IS authorized — proving the gate is the
        // discriminator, not some unrelated config.
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(TileResource::canUserPublishToCore());

        Http::assertNothingSent();
    }

    public function test_bulk_publish_action_publishes_multiple_selected_tiles(): void
    {
        app()->setLocale('de');

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $tileA = $this->makePublishableTile([
            'title' => ['de' => 'Indikator A', 'en' => 'Indicator A'],
            'slug' => ['de' => 'indikator-a', 'en' => 'indicator-a'],
        ]);
        $tileB = $this->makePublishableTile([
            'title' => ['de' => 'Indikator B', 'en' => 'Indicator B'],
            'slug' => ['de' => 'indikator-b', 'en' => 'indicator-b'],
        ]);

        Livewire::test(ListTiles::class)
            ->callTableBulkAction('publishToCore', [$tileA, $tileB])
            ->assertNotified(__('filament.resources.tile.actions.publish_bulk_result'));

        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tileA->fresh()->external_source);
        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $tileB->fresh()->external_source);

        // Two distinct entities were POSTed to the broker.
        $entityPosts = 0;
        Http::recorded(function (Request $request) use (&$entityPosts) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/entities')) {
                $entityPosts++;
            }

            return false;
        });
        $this->assertSame(2, $entityPosts);
    }

    public function test_bulk_publish_action_is_hidden_and_denied_for_non_admin(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $this->actingAs($nonAdmin);

        Http::fake();

        $tile = $this->makePublishableTile();

        // The bulk action is not rendered for a non-admin...
        Livewire::test(ListTiles::class)
            ->assertTableBulkActionHidden('publishToCore');

        // ...and the shared server-side gate denies the non-admin, blocking a
        // direct invocation.
        $this->assertFalse(TileResource::canUserPublishToCore());

        // An admin in the same context IS authorized.
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(TileResource::canUserPublishToCore());

        Http::assertNothingSent();
    }

    public function test_bulk_publish_action_rejects_batches_over_the_limit(): void
    {
        app()->setLocale('de');

        $this->mock(WritableDataSourceInterface::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('upsertEntity');
        });

        $tiles = collect(range(1, TileResource::PUBLISH_BULK_MAX + 1))->map(
            fn (int $i) => $this->makePublishableTile([
                'title' => ['de' => "Indikator {$i}", 'en' => "Indicator {$i}"],
                'slug' => ['de' => "indikator-{$i}", 'en' => "indicator-{$i}"],
            ])
        );

        Livewire::test(ListTiles::class)
            ->callTableBulkAction('publishToCore', $tiles)
            ->assertNotified(__('filament.resources.tile.actions.publish_bulk_too_many', [
                'max' => TileResource::PUBLISH_BULK_MAX,
                'count' => $tiles->count(),
            ]));

        foreach ($tiles as $tile) {
            $this->assertNull($tile->fresh()->external_source);
        }
    }

    public function test_bulk_publish_action_only_publishes_tiles_of_the_current_tenant(): void
    {
        app()->setLocale('de');

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response('', 201),
        ]);

        $ownTile = $this->makePublishableTile();

        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
        $foreignTile = Tile::factory()->create([
            'tenant_id' => $otherTenant->id,
            'title' => ['de' => 'Fremde Kachel', 'en' => 'Foreign Tile'],
            'slug' => ['de' => 'fremde-kachel', 'en' => 'foreign-tile'],
            'time_granularity' => 'year',
        ]);
        $definition = MetricDefinition::factory()->forTile($foreignTile)->create(['metric_key' => 'co2', 'unit' => ['de' => 't', 'en' => 't']]);
        $timePeriod = TimePeriod::factory()->forTile($foreignTile)->create(['period_key' => '2023']);
        MetricValue::factory()->forDefinition($definition)->forTimePeriod($timePeriod)->create(['value' => 1.5]);
        $foreignTile = $foreignTile->fresh();

        // Filament's table query is scoped to the current tenant, so a foreign
        // record id is rejected before the bulk action runs.
        Livewire::test(ListTiles::class)
            ->callTableBulkAction('publishToCore', [$ownTile, $foreignTile]);

        $this->assertSame(NgsiLdDataMapper::SOURCE_KEY, $ownTile->fresh()->external_source);
        $this->assertNull($foreignTile->fresh()->external_source);

        $entityPosts = 0;
        Http::recorded(function (Request $request) use (&$entityPosts) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/entities')) {
                $entityPosts++;
            }

            return false;
        });
        $this->assertSame(1, $entityPosts);
    }

    public function test_publish_error_notification_does_not_leak_broker_detail(): void
    {
        app()->setLocale('de');

        // Broker rejects with a body that embeds the IdP/token URL — exactly the
        // kind of detail that must NOT reach the user-facing notification.
        $leakyDetail = 'token endpoint https://keycloak.example.com/token returned invalid_grant for client dashboard';

        Http::fake([
            'keycloak.example.com/token' => Http::response(['access_token' => 'tok-123']),
            'broker.example.com/context/ngsi-ld/entities' => Http::response(['detail' => $leakyDetail], 422),
        ]);

        $tile = $this->makePublishableTile();

        Livewire::test(EditTile::class, ['record' => $tile->getRouteKey()])
            ->callAction('publishToCore')
            ->assertNotified(__('filament.resources.tile.actions.publish_error'))
            // The generic body carries only the HTTP status, never the raw detail.
            ->assertDontSee($leakyDetail)
            ->assertDontSee('keycloak.example.com');

        // Nothing was stamped on a failed publish.
        $this->assertNull($tile->fresh()->external_source);
    }
}
