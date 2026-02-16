<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TilesApiLocaleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_german_locale_returns_german_translations(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'description' => ['de' => 'Energieverbrauch', 'en' => 'Energy consumption'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertStatus(200);

        $tile = $response->json('data.0');
        $this->assertEquals('Energie', $tile['title']);
        $this->assertEquals('Energieverbrauch', $tile['description']);
        $this->assertEquals('energie', $tile['slug']);
    }

    public function test_english_locale_returns_english_translations(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'description' => ['de' => 'Energieverbrauch', 'en' => 'Energy consumption'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/tiles?locale=en');

        $response->assertStatus(200);

        $tile = $response->json('data.0');
        $this->assertEquals('Energy', $tile['title']);
        $this->assertEquals('Energy consumption', $tile['description']);
        $this->assertEquals('energy', $tile['slug']);
    }

    public function test_no_locale_returns_all_translations(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Energie', 'en' => 'Energy'],
            'description' => ['de' => 'Energieverbrauch', 'en' => 'Energy consumption'],
            'slug' => ['de' => 'energie', 'en' => 'energy'],
            'position' => 0,
        ]);

        $response = $this->getJson('/api/tiles');

        $response->assertStatus(200);

        $tile = $response->json('data.0');
        $this->assertEquals(['de' => 'Energie', 'en' => 'Energy'], $tile['title']);
        $this->assertEquals(['de' => 'Energieverbrauch', 'en' => 'Energy consumption'], $tile['description']);
        $this->assertEquals(['de' => 'energie', 'en' => 'energy'], $tile['slug']);
    }

    public function test_single_tile_endpoint_with_locale_de(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'description' => ['de' => 'Verkehrswende', 'en' => 'Transport transition'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
            'position' => 0,
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=de");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Mobilität')
            ->assertJsonPath('data.description', 'Verkehrswende')
            ->assertJsonPath('data.slug', 'mobilitaet');
    }

    public function test_single_tile_endpoint_with_locale_en(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'description' => ['de' => 'Verkehrswende', 'en' => 'Transport transition'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
            'position' => 0,
        ]);

        $response = $this->getJson("/api/tiles/{$tile->id}?locale=en");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Mobility')
            ->assertJsonPath('data.description', 'Transport transition')
            ->assertJsonPath('data.slug', 'mobility');
    }

    public function test_private_tiles_are_excluded(): void
    {
        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Öffentlich', 'en' => 'Public'],
            'is_public' => true,
            'position' => 0,
        ]);

        Tile::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Privat', 'en' => 'Private'],
            'is_public' => false,
            'position' => 1,
        ]);

        $response = $this->getJson('/api/tiles?locale=de');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Öffentlich');
    }
}
