<?php

namespace Tests\Feature\Import;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use App\Services\Integration\NgsiLdDataMapper;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Covers the provenance guard and the whereTranslation slug-lookup
 * consistency in BundleImporter::upsertTile().
 */
class BundleImporterProvenanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Regensburg', 'slug' => 'regensburg']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        Filament::auth()->logout();
        parent::tearDown();
    }

    private function token(): string
    {
        $token = $this->user->createToken('import-test', ['admin-api']);
        $token->accessToken->tenant_id = $this->tenant->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    private function uploadBundle(string $token, array $bundle, string $mode = 'commit')
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

    private function seedTile(array $attributes, ?array $provenance = null): Tile
    {
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
        $tile = Tile::create($attributes);
        if ($provenance !== null) {
            // external_source is guarded (not fillable); stamp it directly, as
            // PublishService/SyncService do for externally-provenanced tiles.
            $tile->forceFill($provenance)->save();
        }
        Filament::setTenant(null);
        Filament::auth()->logout();

        return $tile->fresh();
    }

    public function test_existing_local_tile_is_found_and_updated_via_where_translation(): void
    {
        // A locally-owned tile (no external_source) must still be
        // matched by its de-slug and updated — proving the whereTranslation
        // lookup returns the same row the old `slug->de` arrow did.
        $tile = $this->seedTile([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
        ]);

        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'energie',
                'tile.title' => ['de' => 'Energie aktualisiert'],
            ]],
        ];

        $response = $this->uploadBundle($this->token(), $bundle, 'commit');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        // No new tile created; the existing one was updated in place.
        $this->assertSame(1, Tile::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
        $tile->refresh();
        $this->assertSame('Energie aktualisiert', $tile->getTranslation('title', 'de'));
        $this->assertSame('Energy', $tile->getTranslation('title', 'en'), 'en translation must be preserved');
    }

    public function test_foreign_provenance_tile_is_skipped_with_warning_and_import_continues(): void
    {
        // A tile owned by a foreign external source must NOT be
        // overwritten. The importer skips it, logs a warning, and continues
        // processing the remaining (locally-owned) rows.
        $foreign = $this->seedTile(
            [
                'title' => ['de' => 'Fremd verwaltet', 'en' => 'Foreign owned'],
                'slug' => ['de' => 'fremd', 'en' => 'foreign'],
            ],
            ['external_source' => 'some-other-system', 'external_id' => 'urn:foreign:1'],
        );

        // Capture log records without replacing the Log facade — Log::spy()
        // would break Log::channel('daily')->debug() in the tenant-resolution
        // middleware, which runs on every request.
        $warnings = [];
        Log::listen(function ($message) use (&$warnings) {
            if ($message->level === 'warning') {
                $warnings[] = $message->message.json_encode($message->context);
            }
        });

        $bundle = [
            'schema_version' => '1.0',
            'data' => [
                // Row 1: targets the foreign-provenance tile → must be skipped.
                [
                    'tile.slug' => 'fremd',
                    'tile.title' => ['de' => 'Versuch zu überschreiben'],
                ],
                // Row 2: a brand-new local tile → must import normally.
                [
                    'tile.slug' => 'lokal-neu',
                    'tile.title' => ['de' => 'Lokal neu'],
                ],
            ],
        ];

        $response = $this->uploadBundle($this->token(), $bundle, 'commit');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        // The foreign tile is untouched.
        $foreign->refresh();
        $this->assertSame('Fremd verwaltet', $foreign->getTranslation('title', 'de'), 'foreign-provenance tile must not be overwritten');
        $this->assertSame('some-other-system', $foreign->external_source);

        // A warning naming the skipped slug was logged.
        $this->assertNotEmpty($warnings, 'a warning must be logged for the skipped tile');
        $this->assertTrue(
            collect($warnings)->contains(fn (string $entry) => str_contains($entry, 'fremd')),
            'the logged warning must name the skipped slug'
        );

        // The second, locally-owned row still imported.
        $this->assertDatabaseHas('tiles', ['tenant_id' => $this->tenant->id]);
        $this->assertSame(
            1,
            Tile::withoutGlobalScope('tenant')
                ->where('tenant_id', $this->tenant->id)
                ->whereTranslation('slug', 'de', 'lokal-neu')
                ->count(),
            'the new local tile must be created despite the skipped foreign tile'
        );
    }

    public function test_civitas_core_provenance_tile_is_updated_normally(): void
    {
        // A tile already provenanced to civitas-core is NOT foreign — the
        // importer (re-)owns it locally and may update it, mirroring the
        // PublishService rule that civitas-core tiles remain writable.
        $tile = $this->seedTile(
            [
                'title' => ['de' => 'Aus CORE', 'en' => 'From CORE'],
                'slug' => ['de' => 'aus-core'],
            ],
            ['external_source' => NgsiLdDataMapper::SOURCE_KEY, 'external_id' => 'urn:ngsi-ld:Indicator:1'],
        );

        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'aus-core',
                'tile.title' => ['de' => 'Aus CORE aktualisiert'],
            ]],
        ];

        $response = $this->uploadBundle($this->token(), $bundle, 'commit');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $tile->refresh();
        $this->assertSame('Aus CORE aktualisiert', $tile->getTranslation('title', 'de'));
    }

    public function test_brand_new_slug_creates_tile_normally(): void
    {
        $bundle = [
            'schema_version' => '1.0',
            'data' => [[
                'tile.slug' => 'ganz-neu',
                'tile.title' => ['de' => 'Ganz neu'],
            ]],
        ];

        $response = $this->uploadBundle($this->token(), $bundle, 'commit');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(
            1,
            Tile::withoutGlobalScope('tenant')
                ->where('tenant_id', $this->tenant->id)
                ->whereTranslation('slug', 'de', 'ganz-neu')
                ->count()
        );
    }
}
