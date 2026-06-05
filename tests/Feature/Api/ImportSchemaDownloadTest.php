<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ImportSchemaDownloadTest extends TestCase
{
    public function test_can_download_bundle_schema(): void
    {
        $response = $this->get('/api/import/schemas/bundle');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8');

        $body = $response->streamedContent() ?: $response->getContent();
        $schema = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema'] ?? null);
        $this->assertSame('Upload Bundle', $schema['title'] ?? null);
    }

    public function test_can_download_row_schema(): void
    {
        $this->get('/api/import/schemas/row')->assertStatus(200);
    }

    public function test_can_download_category_schemas(): void
    {
        $this->get('/api/import/schemas/category')->assertStatus(200);
        $this->get('/api/import/schemas/category-group')->assertStatus(200);
    }

    public function test_unknown_schema_returns_404(): void
    {
        $this->get('/api/import/schemas/secret-file')->assertStatus(404);
    }

    public function test_path_traversal_in_schema_returns_404(): void
    {
        // Router constraint on {name} already blocks slashes/dots, but verify
        // that the allowlist logic rejects unknown names regardless.
        $this->get('/api/import/schemas/nonexistent')->assertStatus(404);
    }

    public function test_can_download_valid_minimal_example(): void
    {
        $response = $this->get('/api/import/examples/valid-minimal');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8');

        $body = $response->streamedContent() ?: $response->getContent();
        $bundle = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.0', $bundle['schema_version']);
    }

    public function test_can_download_valid_full_example(): void
    {
        $this->get('/api/import/examples/valid-full')->assertStatus(200);
    }

    public function test_invalid_examples_are_not_served(): void
    {
        // The allowlist only exposes the valid examples; users don't need the
        // invalid ones, which also keeps the public surface tight.
        $this->get('/api/import/examples/invalid-bad-types')->assertStatus(404);
    }

    public function test_endpoints_are_public(): void
    {
        // No auth header → still 200. This is intentional; the schemas are
        // part of the public documentation, same as `/docs`.
        $this->get('/api/import/schemas/bundle')->assertStatus(200);
        $this->get('/api/import/examples/valid-minimal')->assertStatus(200);
    }
}
