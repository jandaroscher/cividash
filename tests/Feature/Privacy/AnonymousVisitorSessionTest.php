<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Privacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Anonymous visitors of the public dashboard and of embedded tiles must not get a
 * session: no cookie, and no row in the sessions table (which stores IP and user agent).
 */
class AnonymousVisitorSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public static function publicPaths(): array
    {
        return [
            'start page' => ['/'],
            'english start page' => ['/en'],
            'content page' => ['/some/page'],
            'tile embed' => ['/embed/tiles/some-tile'],
            'english tile embed' => ['/en/embed/tiles/some-tile'],
            'security.txt' => ['/.well-known/security.txt'],
        ];
    }

    #[DataProvider('publicPaths')]
    public function test_public_page_sets_no_cookie_and_stores_no_session(string $path): void
    {
        $response = $this->get($path)->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_public_api_sets_no_cookie_and_stores_no_session(): void
    {
        $response = $this->getJson('/api/v1/tiles')->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_admin_login_still_starts_a_session(): void
    {
        $response = $this->get('/admin/login')->assertOk();

        $this->assertNotSame([], $response->headers->getCookies());
    }
}
