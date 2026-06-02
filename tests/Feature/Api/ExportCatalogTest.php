<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $defaultTenant;

    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTenant = Tenant::where('slug', 'default')->firstOrFail();
        $this->otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant']);

        Tile::factory()->forTenant($this->defaultTenant)->create([
            'title' => ['de' => 'Eins', 'en' => 'One'],
            'slug' => ['de' => 'eins', 'en' => 'one'],
            'position' => 1,
        ]);
        Tile::factory()->forTenant($this->defaultTenant)->create([
            'title' => ['de' => 'Zwei', 'en' => 'Two'],
            'slug' => ['de' => 'zwei', 'en' => 'two'],
            'position' => 2,
        ]);
        Tile::factory()->forTenant($this->otherTenant)->create([
            'title' => ['de' => 'Geheim', 'en' => 'Secret'],
            'slug' => ['de' => 'geheim', 'en' => 'secret'],
            'position' => 1,
        ]);
    }

    public function test_catalog_returns_only_default_tenant_tiles(): void
    {
        $response = $this->getJson('/api/exports/catalog?format=json&locale=de');

        $response->assertOk();
        $response->assertHeader('X-Export-Schema-Version', '1.0');

        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('default', $body['tenant']['slug']);
        $this->assertCount(2, $body['data']);

        $titles = array_column($body['data'], 'tile.title');
        $this->assertSame(['Eins', 'Zwei'], $titles);
        $this->assertNotContains('Geheim', $titles);
    }

    public function test_catalog_csv_streams_attachment_with_filename(): void
    {
        $response = $this->get('/api/exports/catalog?format=csv&locale=de');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/attachment; filename="catalog-default-\d{8}-\d{6}\.csv"/',
            (string) $response->headers->get('Content-Disposition'),
        );

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Eins', $body);
        $this->assertStringContainsString('Zwei', $body);
        $this->assertStringNotContainsString('Geheim', $body);
    }

    public function test_empty_tenant_returns_valid_envelope_with_empty_data(): void
    {
        Tile::where('tenant_id', $this->defaultTenant->id)->delete();

        $response = $this->getJson('/api/exports/catalog?format=json');

        $response->assertOk();
        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.0', $body['schema_version']);
        $this->assertSame([], $body['data']);
    }

    public function test_english_locale_returns_english_titles(): void
    {
        $response = $this->getJson('/api/exports/catalog?format=json&locale=en');

        $body = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('en', $body['locale']);
        $titles = array_column($body['data'], 'tile.title');
        $this->assertSame(['One', 'Two'], $titles);
    }
}
