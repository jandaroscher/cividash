<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tile $tile;

    protected function setUp(): void
    {
        parent::setUp();

        // Base TestCase already creates the 'default' tenant (slug fallback used
        // by ResolveTenantFromRequest when no token/domain matches).
        $this->tenant = Tenant::where('slug', 'default')->firstOrFail();

        $this->tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
            'description' => ['de' => '<p>Verkehr und Infrastruktur</p>', 'en' => '<p>Transport and infrastructure</p>'],
            'hint' => ['de' => 'Radverkehr', 'en' => 'Cycling'],
            'position' => 1,
        ]);

        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'topics',
            'title' => ['de' => 'Themen', 'en' => 'Topics'],
            'is_active' => true,
        ]);
        $category = Category::factory()->forGroup($group)->create([
            'key' => 'mobility',
            'slug' => ['de' => 'Verkehr', 'en' => 'Transport'],
            'is_active' => true,
        ]);
        $this->tile->categories()->attach($category);

        $md = MetricDefinition::factory()->forTile($this->tile)->create([
            'metric_key' => 'co2',
            'label' => ['de' => 'CO₂-Emissionen', 'en' => 'CO₂ emissions'],
            'unit' => ['de' => 't', 'en' => 't'],
            'indicator_type' => 'big',
        ]);

        $year2020 = TimePeriod::factory()->forTile($this->tile)->year(2020)->create();
        $year2021 = TimePeriod::factory()->forTile($this->tile)->year(2021)->create();

        MetricValue::factory()->forDefinition($md)->forTimePeriod($year2020)->create(['value' => 100.50]);
        MetricValue::factory()->forDefinition($md)->forTimePeriod($year2021)->create(['value' => 95.25]);
    }

    public function test_json_export_returns_envelope_with_schema_version(): void
    {
        $response = $this->getJson('/api/tiles/mobilitaet/export?format=json&locale=de');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('X-Export-Schema-Version', '1.0');
        $this->assertStringContainsString('attachment; filename="tile-mobilitaet-', $response->headers->get('Content-Disposition'));

        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.0', $body['schema_version']);
        $this->assertSame('de', $body['locale']);
        $this->assertSame('default', $body['tenant']['slug']);
        $this->assertSame('mobilitaet', $body['filter']['tile_slug']);
        $this->assertCount(2, $body['data']);
        $this->assertSame('Mobilität', $body['data'][0]['tile.title']);
        $this->assertSame('CO₂-Emissionen', $body['data'][0]['metric.label']);
        $this->assertSame(2020, $body['data'][0]['value.year']);
        // Internal config fields are no longer part of the schema.
        $this->assertArrayNotHasKey('metric.key', $body['data'][0]);
        $this->assertArrayNotHasKey('tile.position', $body['data'][0]);
        $this->assertArrayNotHasKey('value.sort_order', $body['data'][0]);
        $this->assertSame(100.5, $body['data'][0]['value.value']);
        $this->assertNull($body['data'][0]['metric.source']);
        $this->assertNull($body['data'][0]['metric.methodology']);
        $this->assertNull($body['data'][0]['metric.formula']);
    }

    public function test_csv_export_has_bom_semicolon_and_localized_headers(): void
    {
        $response = $this->get('/api/tiles/mobilitaet/export?format=csv&locale=de');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'CSV must start with UTF-8 BOM');
        $lines = preg_split('/\r?\n/', substr($body, 3));
        $this->assertStringContainsString('Kachel-ID;Kachel-Slug;Kachel', $lines[0]);
        $this->assertStringContainsString('Mobilität', $body);
        $this->assertStringContainsString('CO₂-Emissionen', $body);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/tiles/does-not-exist/export')->assertNotFound();
    }

    public function test_tile_from_another_tenant_is_not_accessible(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant']);
        Tile::factory()->forTenant($other)->create([
            'title' => ['de' => 'Geheim', 'en' => 'Secret'],
            'slug' => ['de' => 'geheim', 'en' => 'secret'],
        ]);

        // Default-tenant request must not see the 'other' tile.
        $this->getJson('/api/tiles/geheim/export')->assertNotFound();
    }

    public function test_invalid_format_returns_422(): void
    {
        $this->getJson('/api/tiles/mobilitaet/export?format=xml')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['format']]);
    }

    public function test_field_whitelist_limits_exported_columns(): void
    {
        $response = $this->getJson(
            '/api/tiles/mobilitaet/export?format=json&fields[]=tile.title&fields[]=metric.label&fields[]=value.value'
        );

        $response->assertOk();
        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['tile.title', 'metric.label', 'value.value'], $body['fields']);
        $row = $body['data'][0];
        $this->assertArrayHasKey('tile.title', $row);
        $this->assertArrayHasKey('metric.label', $row);
        $this->assertArrayHasKey('value.value', $row);
        $this->assertArrayNotHasKey('tile.description', $row);
        $this->assertArrayNotHasKey('metric.methodology', $row);
    }

    public function test_fields_with_only_unknown_values_returns_422(): void
    {
        $this->getJson('/api/tiles/mobilitaet/export?fields[]=bogus&fields[]=also_bad')
            ->assertStatus(422)
            ->assertJsonPath('message', trans('export.errors.no_valid_fields', [], 'de'));
    }
}
