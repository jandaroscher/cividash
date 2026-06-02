<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Tile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dedicated client IP so the named `export` rate limiter bucket is
     * isolated from other tests. The limiter keys on $request->ip().
     */
    private const TEST_IP = '203.0.113.42';

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::where('slug', 'default')->firstOrFail();
        Tile::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Eins', 'en' => 'One'],
            'slug' => ['de' => 'eins', 'en' => 'one'],
        ]);
    }

    public function test_export_endpoint_enforces_ten_requests_per_minute(): void
    {
        $request = fn () => $this
            ->withServerVariables(['REMOTE_ADDR' => self::TEST_IP])
            ->getJson('/api/exports/catalog?format=json');

        for ($i = 0; $i < 30; $i++) {
            $request()->assertOk();
        }

        $request()->assertStatus(429);
    }
}
