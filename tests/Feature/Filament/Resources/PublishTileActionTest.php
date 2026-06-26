<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\TileResource\Pages\EditTile;
use App\Filament\Resources\TileResource\Pages\ListTiles;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\Integration\NgsiLdDataMapper;
use App\Settings\IntegrationSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PublishTileActionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $user = \App\Models\User::factory()->admin()->create();
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

    private function makePublishableTile(): Tile
    {
        $tile = Tile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'],
            'slug' => ['de' => 'co2-emissionen', 'en' => 'co2-emissions'],
            'time_granularity' => 'year',
        ]);

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
}
