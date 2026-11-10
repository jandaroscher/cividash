<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded media on the public disk is reachable under /storage/<path> on the app,
 * whatever driver backs the disk. With an object storage that does not allow
 * anonymous reads, the browser cannot load media from the bucket itself.
 */
class PublicMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_serves_a_file_from_the_public_disk(): void
    {
        Storage::disk('public')->put('page-blocks/photo.png', 'png-bytes');

        $response = $this->get('/storage/page-blocks/photo.png');

        $response->assertOk();
        $this->assertSame('png-bytes', $response->streamedContent());
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $response->assertCookieMissing(config('session.cookie'));
    }

    public function test_returns_404_for_a_missing_file(): void
    {
        $this->get('/storage/page-blocks/missing.png')->assertNotFound();
    }

    public function test_does_not_leave_the_public_disk(): void
    {
        Storage::disk('local')->put('secret.txt', 'secret');

        $this->get('/storage/..%2F..%2Fapp%2Fprivate%2Fsecret.txt')->assertNotFound();
    }
}
