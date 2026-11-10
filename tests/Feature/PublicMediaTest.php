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

    public function test_s3_media_urls_point_to_the_object_key_without_a_filesystem_path(): void
    {
        $previous = [$_SERVER['PUBLIC_DISK_DRIVER'] ?? null, $_ENV['PUBLIC_DISK_DRIVER'] ?? null];
        $_SERVER['PUBLIC_DISK_DRIVER'] = $_ENV['PUBLIC_DISK_DRIVER'] = 's3';
        try {
            $config = (require config_path('filesystems.php'))['disks']['public'];
        } finally {
            [$server, $env] = $previous;
            if ($server === null) {
                unset($_SERVER['PUBLIC_DISK_DRIVER']);
            } else {
                $_SERVER['PUBLIC_DISK_DRIVER'] = $server;
            }
            if ($env === null) {
                unset($_ENV['PUBLIC_DISK_DRIVER']);
            } else {
                $_ENV['PUBLIC_DISK_DRIVER'] = $env;
            }
        }

        $disk = Storage::build(array_merge($config, [
            'key' => 'key', 'secret' => 'secret', 'region' => 'us-east-1', 'bucket' => 'media',
            'url' => 'https://dashboard.example.org/storage',
        ]));

        $this->assertSame('https://dashboard.example.org/storage/tiles/icon.svg', $disk->url('tiles/icon.svg'));
    }

    public function test_does_not_leave_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secret.txt', 'secret');

        // The fake disks are sibling directories, so this would reach the private disk.
        $this->get('/storage/..%2Flocal%2Fsecret.txt')->assertNotFound();
    }
}
