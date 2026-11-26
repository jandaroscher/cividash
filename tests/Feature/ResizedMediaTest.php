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
        $this->get('/storage/_resized/960/_resized/960/page-blocks/missing.jpg')->assertNotFound();
    }

    public function test_keeps_png_transparency(): void
    {
        $image = imagecreatetruecolor(2000, 1000);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('page-blocks/logo.png', ob_get_clean());

        $scaled = imagecreatefromstring($this->get('/storage/_resized/480/page-blocks/logo.png')->streamedContent());

        $this->assertGreaterThan(120, imagecolorsforindex($scaled, imagecolorat($scaled, 10, 10))['alpha']);
    }

    public function test_applies_the_exif_orientation_of_phone_photos(): void
    {
        // A landscape JPEG whose EXIF says "rotate 90° clockwise", as phones store portrait photos.
        $image = imagecreatetruecolor(3000, 2000);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        $tiff = "II*\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00\x00\x00\x00\x00";
        $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\x00\x00".$tiff;
        Storage::disk('public')->put('page-blocks/portrait.jpg', substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        $response = $this->get('/storage/_resized/960/page-blocks/portrait.jpg');

        $this->assertSame([960, 1440], array_slice(getimagesizefromstring($response->streamedContent()), 0, 2));
    }

    public function test_stores_images_it_does_not_scale_so_the_web_server_can_serve_them(): void
    {
        $this->putImage('page-blocks/small.jpg', 400, 300);
        $this->putImage('page-blocks/tall.jpg', 1700, 24000);

        $small = $this->get('/storage/_resized/480/page-blocks/small.jpg');
        $this->get('/storage/_resized/1600/page-blocks/tall.jpg')->assertOk();

        $this->assertStringContainsString('sandbox', $small->headers->get('Content-Security-Policy'));
        $this->assertTrue(Storage::disk('public')->exists('_resized/480/page-blocks/small.jpg'));
        $this->assertSame(1700, getimagesizefromstring(Storage::disk('public')->get('_resized/1600/page-blocks/tall.jpg'))[0]);
        $this->assertSame([], Storage::disk('public')->allFiles('_resized/.tmp'));
    }

    public function test_does_not_scale_copies_again(): void
    {
        $this->putImage('_resized/960/page-blocks/slide.jpg', 960, 640);

        $this->get('/storage/_resized/480//_resized/960/page-blocks/slide.jpg')->assertNotFound();
    }

    public function test_limits_new_copies_per_client_but_not_stored_ones(): void
    {
        $this->putImage('page-blocks/slide.jpg', 600, 400);
        $this->get('/storage/_resized/480/page-blocks/slide.jpg')->assertOk();
        for ($i = 0; $i < 60; $i++) {
            $this->putImage("page-blocks/many-{$i}.jpg", 600, 400);
            $this->get("/storage/_resized/480/page-blocks/many-{$i}.jpg");
        }

        $this->putImage('page-blocks/one-more.jpg', 600, 400);
        $this->get('/storage/_resized/480/page-blocks/one-more.jpg')->assertStatus(429);
        $this->get('/storage/_resized/480/page-blocks/slide.jpg')->assertOk();
    }
}
