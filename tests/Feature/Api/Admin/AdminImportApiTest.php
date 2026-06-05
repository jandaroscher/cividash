<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\ImportRun;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Regensburg', 'slug' => 'regensburg']);
        $this->otherTenant = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach([$this->tenant->id, $this->otherTenant->id]);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        Filament::auth()->logout();
        parent::tearDown();
    }

    private function token(Tenant $tenant): string
    {
        $token = $this->user->createToken('import-test', ['admin-api']);
        $token->accessToken->tenant_id = $tenant->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    private function uploadBundle(string $token, array $bundle, string $mode = 'dry_run')
    {
        $json = json_encode($bundle, JSON_THROW_ON_ERROR);
        $file = UploadedFile::fake()->createWithContent('bundle.json', $json);

        return $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post('/api/admin/import', [
            'file' => $file,
            'mode' => $mode,
        ]);
    }

    private function postRaw(array $data, ?string $token = null)
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->withHeaders($headers)->post('/api/admin/import', $data);
    }

    public function test_dry_run_creates_nothing_but_reports_diff(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [
                [
                    'tile.slug' => 'energieverbrauch',
                    'tile.title' => ['de' => 'Energieverbrauch', 'en' => 'Energy'],
                ],
            ],
        ];

        $response = $this->uploadBundle($token, $bundle, 'dry_run');

        $response->assertStatus(200)
            ->assertJson([
                'mode' => 'dry_run',
                'status' => 'success',
            ])
            ->assertJsonPath('diff.tiles.create', 1);

        $this->assertSame(0, Tile::withoutGlobalScope('tenant')->count(), 'dry_run must not persist tiles');
    }

    public function test_commit_creates_tiles_metrics_and_values(): void
    {
        $token = $this->token($this->tenant);

        // Seed category structure first so the tile can link to it.
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
        $group = CategoryGroup::create(['key' => 'handlungsfelder', 'title' => ['de' => 'Handlungsfelder']]);
        Category::create([
            'category_group_id' => $group->id,
            'key' => 'energie',
            'slug' => ['de' => 'energie', 'en' => 'energy'],
        ]);
        Filament::setTenant(null);
        Filament::auth()->logout();

        $bundle = [
            'schema_version' => '1.0',
            'data' => [
                [
                    'tile.slug' => 'energieverbrauch',
                    'tile.title' => ['de' => 'Energieverbrauch', 'en' => 'Energy'],
                    'category.keys' => ['handlungsfelder/energie'],
                    'metric.key' => 'kwh_total',
                    'metric.label' => ['de' => 'Gesamtverbrauch'],
                    'metric.unit' => ['de' => 'GWh'],
                    'value.year' => 2024,
                    'value.value' => 3750,
                ],
            ],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('tiles', ['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('metric_definitions', ['metric_key' => 'kwh_total', 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('time_periods', ['period_key' => '2024', 'granularity' => 'year', 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('metric_values', ['tenant_id' => $this->tenant->id]);

        $tile = Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->first();
        $this->assertCount(1, $tile->categories, 'tile must have its category attached');
    }

    public function test_commit_is_idempotent(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'mobilitaet',
                'tile.title' => ['de' => 'Mobilität'],
                'metric.key' => 'bikes',
                'metric.label' => ['de' => 'Fahrräder'],
                'value.year' => 2024,
                'value.value' => 120,
            ]],
        ];

        $this->uploadBundle($token, $bundle, 'commit')->assertStatus(200);
        $this->uploadBundle($token, $bundle, 'commit')->assertStatus(200);

        $this->assertSame(1, Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, MetricDefinition::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, TimePeriod::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, MetricValue::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_missing_tile_title_on_new_tile_returns_422_and_rolls_back(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [
                // First tile is valid.
                ['tile.slug' => 'ok', 'tile.title' => ['de' => 'OK']],
                // Second tile is new but has no title → fachlich invalid.
                ['tile.slug' => 'broken'],
            ],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');

        $response->assertStatus(422)
            ->assertJson(['status' => 'failed'])
            ->assertJsonFragment(['code' => 'required']);

        // Neither tile must exist — atomic rollback.
        $this->assertSame(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_unresolved_category_reference_returns_422(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'wasser',
                'tile.title' => ['de' => 'Wasser'],
                'category.keys' => ['unbekannt/xyz'],
            ]],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');

        $response->assertStatus(422)
            ->assertJsonFragment(['code' => 'unresolved_reference']);

        $this->assertSame(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_tenant_mismatch_produces_warning_but_imports_into_token_tenant(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'tenant' => ['slug' => 'demo-city'],
            'data' => [[
                'tile.slug' => 'luft',
                'tile.title' => ['de' => 'Luft'],
            ]],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');

        $response->assertStatus(200)
            ->assertJsonFragment(['code' => 'tenant_mismatch']);

        // Imported into token tenant (regensburg), not demo-city.
        $this->assertDatabaseHas('tiles', ['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseMissing('tiles', ['tenant_id' => $this->otherTenant->id]);
    }

    public function test_tenant_isolation_prevents_reading_other_tenant_tiles(): void
    {
        // Seed a tile in otherTenant
        Filament::auth()->login($this->user);
        Filament::setTenant($this->otherTenant);
        Tile::create(['title' => ['de' => 'Nur Demo City'], 'slug' => ['de' => 'nur-demo-city']]);
        Filament::setTenant(null);
        Filament::auth()->logout();

        $token = $this->token($this->tenant); // Regensburg token

        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'nur-demo-city',
                'tile.title' => ['de' => 'Neu angelegt in Regensburg'],
            ]],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        // Two tiles total — one per tenant — same slug is OK because tenant-scoped.
        $this->assertSame(2, Tile::withoutGlobalScope('tenant')->where('slug->de', 'nur-demo-city')->count());
    }

    public function test_invalid_json_returns_422(): void
    {
        $token = $this->token($this->tenant);
        $file = UploadedFile::fake()->createWithContent('broken.json', '{ not json');

        $response = $this->postRaw(['file' => $file, 'mode' => 'dry_run'], $token);

        $response->assertStatus(422)->assertJsonFragment(['code' => 'invalid_json']);
    }

    public function test_schema_violation_returns_422(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '2.0', // Invalid — only 1.0 allowed.
            'data' => [],
        ];

        $response = $this->uploadBundle($token, $bundle, 'dry_run');

        $response->assertStatus(422)
            ->assertJsonFragment(['code' => 'invalid_enum_value']);
    }

    public function test_import_run_is_persisted(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [['tile.slug' => 'foo', 'tile.title' => ['de' => 'Foo']]],
        ];

        $this->uploadBundle($token, $bundle, 'commit')->assertStatus(200);

        $runs = ImportRun::withoutGlobalScope('tenant')->get();
        $this->assertCount(1, $runs);
        $this->assertSame('success', $runs->first()->status);
        $this->assertSame('commit', $runs->first()->mode);
        $this->assertSame($this->tenant->id, $runs->first()->tenant_id);
    }

    public function test_request_without_token_returns_401(): void
    {
        $file = UploadedFile::fake()->createWithContent('b.json', '{}');
        $response = $this->postRaw(['file' => $file, 'mode' => 'dry_run']);
        $response->assertStatus(401);
    }

    public function test_token_without_admin_api_ability_returns_403(): void
    {
        // Create a token with a non-admin ability (not `['*']` because Sanctum treats
        // that as "all abilities" including admin-api).
        $token = $this->user->createToken('no-admin', ['basic-user'])->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('b.json', '{}');

        $response = $this->postRaw(['file' => $file, 'mode' => 'dry_run'], $token);

        $response->assertStatus(403);
    }

    public function test_mode_is_required(): void
    {
        $token = $this->token($this->tenant);
        $file = UploadedFile::fake()->createWithContent('b.json', '{}');

        $response = $this->postRaw(['file' => $file], $token);

        $response->assertStatus(422)->assertJsonValidationErrors(['mode']);
    }

    public function test_replace_mode_is_rejected_as_unsupported(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'mode' => 'replace',
            'data' => [['tile.slug' => 'foo', 'tile.title' => ['de' => 'Foo']]],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');

        $response->assertStatus(422)
            ->assertJsonFragment(['code' => 'unsupported_mode']);

        $this->assertSame(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_partial_translation_does_not_wipe_other_locales(): void
    {
        // Seed a tile with both de + en translations.
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
        $tile = Tile::create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'description' => ['de' => 'Deutsche Beschreibung', 'en' => 'English description'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
        ]);
        Filament::setTenant(null);
        Filament::auth()->logout();

        $token = $this->token($this->tenant);

        // Import bundle sends only the de variant — en must be preserved.
        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'energie',
                'tile.title' => ['de' => 'Neuer deutscher Titel'],
            ]],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $tile->refresh();
        $this->assertSame('Neuer deutscher Titel', $tile->getTranslation('title', 'de'));
        $this->assertSame('Energy', $tile->getTranslation('title', 'en'), 'en translation must not be wiped by partial update');
    }

    public function test_execution_error_returns_generic_message_not_raw_exception(): void
    {
        // Force a real execution error by registering a Tile::saving event
        // listener that throws with a message containing sensitive-looking
        // internals. The catch block in BundleImporter must swallow the raw
        // message and return a generic one.
        $sensitive = 'SQLSTATE[42S22]: Column not found: secret_internal_detail';
        Tile::saving(fn () => throw new \RuntimeException($sensitive));

        try {
            $token = $this->token($this->tenant);

            $bundle = [
                'schema_version' => '1.0',
                'data' => [['tile.slug' => 'boom', 'tile.title' => ['de' => 'Boom']]],
            ];

            $response = $this->uploadBundle($token, $bundle, 'commit');

            $response->assertStatus(422)
                ->assertJsonFragment(['code' => 'execution_error']);

            $bodyJson = $response->getContent();
            $this->assertStringNotContainsString(
                $sensitive,
                $bodyJson,
                'Raw exception message must not leak into the API response'
            );
            $this->assertStringNotContainsString('SQLSTATE', $bodyJson);

            // Nothing was committed.
            $this->assertSame(0, Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
        } finally {
            // Always restore Tile's default listeners, even if an assertion
            // above throws — otherwise later tests inherit the throwing
            // listener or run without position auto-increment.
            Tile::flushEventListeners();
            Tile::boot();
        }
    }

    public function test_category_sync_preserves_existing_associations(): void
    {
        // Seed tile with two categories; bundle only ships one → the second
        // must still be attached after the import (upsert = additive).
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
        $group = CategoryGroup::create(['key' => 'handlungsfelder', 'title' => ['de' => 'Handlungsfelder']]);
        $catEnergy = Category::create([
            'category_group_id' => $group->id,
            'key' => 'energie',
            'slug' => ['de' => 'energie'],
        ]);
        $catMobility = Category::create([
            'category_group_id' => $group->id,
            'key' => 'mobilitaet',
            'slug' => ['de' => 'mobilitaet'],
        ]);
        $tile = Tile::create([
            'title' => ['de' => 'T'],
            'slug' => ['de' => 'energieverbrauch'],
        ]);
        $tile->categories()->sync([$catEnergy->id, $catMobility->id]);
        Filament::setTenant(null);
        Filament::auth()->logout();

        $token = $this->token($this->tenant);

        // Bundle only mentions one of the two categories.
        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'energieverbrauch',
                'tile.title' => ['de' => 'T'],
                'category.keys' => ['handlungsfelder/energie'],
            ]],
        ];

        $this->uploadBundle($token, $bundle, 'commit')->assertStatus(200);

        $tile->refresh();
        $this->assertEqualsCanonicalizing(
            [$catEnergy->id, $catMobility->id],
            $tile->categories->pluck('id')->all(),
            'Both categories must remain attached — upsert is additive'
        );
    }

    public function test_validation_failure_does_not_use_execution_error_code(): void
    {
        $token = $this->token($this->tenant);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'new-tile',
                'tile.title' => ['de' => 'X'],
                'metric.key' => 'bad',
                // metric.label missing → validation_failed, not execution_error
                'value.year' => 2024,
                'value.value' => 1,
            ]],
        ];

        $response = $this->uploadBundle($token, $bundle, 'commit');
        $response->assertStatus(422);

        foreach ($response->json('errors') as $error) {
            $this->assertNotSame('execution_error', $error['code'] ?? null);
        }
    }
}
