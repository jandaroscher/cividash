<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Content images are served in a few fixed widths under /storage/_resized/<width>/<path>
 * so the frontend can offer a srcset instead of the full-size upload.
 */
class ResizedMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function putImage(string $path, int $width, int $height, string $type = 'jpeg'): void
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        $type === 'png' ? imagepng($image) : imagejpeg($image);
        Storage::disk('public')->put($path, ob_get_clean());
    }

    public function test_serves_a_downscaled_copy_and_keeps_it_on_the_disk(): void
    {
        $this->putImage('page-blocks/slide.jpg', 3000, 2000);

        $response = $this->get('/storage/_resized/960/page-blocks/slide.jpg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        [$width, $height] = getimagesizefromstring($response->streamedContent());
        $this->assertSame([960, 640], [$width, $height]);
        $this->assertTrue(Storage::disk('public')->exists('_resized/960/page-blocks/slide.jpg'));
    }

    public function test_keeps_png_as_png(): void
    {
        $this->putImage('page-blocks/logo.png', 2000, 1000, 'png');

        $response = $this->get('/storage/_resized/480/page-blocks/logo.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertSame(480, getimagesizefromstring($response->streamedContent())[0]);
    }

    public function test_never_upscales_a_smaller_image(): void
    {
        $this->putImage('page-blocks/small.jpg', 400, 300);

        $response = $this->get('/storage/_resized/1600/page-blocks/small.jpg');

        $response->assertOk();
        $this->assertSame(400, getimagesizefromstring($response->streamedContent())[0]);
    }

    public function test_rejects_widths_outside_the_fixed_set(): void
    {
        $this->putImage('page-blocks/slide.jpg', 3000, 2000);

        $this->get('/storage/_resized/1234/page-blocks/slide.jpg')->assertNotFound();
        $this->assertFalse(Storage::disk('public')->exists('_resized/1234/page-blocks/slide.jpg'));
    }

    public function test_returns_404_for_missing_files_non_images_and_traversal(): void
    {
        Storage::disk('public')->put('page-blocks/icon.svg', '<svg/>');

        $this->get('/storage/_resized/960/page-blocks/missing.jpg')->assertNotFound();
        $this->get('/storage/_resized/960/page-blocks/icon.svg')->assertNotFound();
        $this->get('/storage/_resized/960/../.env')->assertNotFound();
        $this->get('/storage/_resized/960/_resized/960/page-blocks/missing.jpg')->assertNotFound();
    }
}
