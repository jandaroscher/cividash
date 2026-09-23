<?php

namespace Tests\Unit;

use App\Services\MediaDownloadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDownloadServiceTest extends TestCase
{
    private MediaDownloadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seeding.media_base_url' => 'https://media.example.org']);
        $this->service = new MediaDownloadService;
    }

    public function test_builds_files_and_assets_urls_from_the_base_url(): void
    {
        Storage::fake('public');
        config(['seeding.media_base_url' => 'https://media.example.org/files/']);

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->exactly(2))
            ->method('downloadFileContent')
            ->willReturnCallback(function (string $url) {
                static $expected = [
                    'https://media.example.org/files/a%20b.png',
                    'https://media.example.org/assets/sdg/goal-1.png',
                ];
                $this->assertSame(array_shift($expected), $url);

                return 'content';
            });

        $service->downloadFile('/fm/1/a%20b.png', 'tiles');
        $service->downloadAsset('sdg/goal-1.png', 'sdg');
    }

    public function test_skips_downloads_without_a_base_url(): void
    {
        Storage::fake('public');
        config(['seeding.media_base_url' => null]);

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->never())->method('downloadFileContent');

        $this->assertNull($service->downloadFile('/fm/1/icon.svg', 'tiles'));
        $this->assertNull($service->downloadAsset('sdg/goal-1.svg', 'sdg'));
    }

    public function test_download_file_saves_to_public_disk(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $result = $service->downloadFile('/fm/496/test-icon.svg', 'tiles');

        $this->assertNotNull($result);
        $this->assertEquals('seeds/tiles/test-icon.svg', $result);
        Storage::disk('public')->assertExists('seeds/tiles/test-icon.svg');
    }

    public function test_download_file_returns_existing_path_when_cached(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('seeds/tiles/cached-file.svg', 'existing-content');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        // downloadFileContent should NOT be called since file exists
        $service->expects($this->never())
            ->method('downloadFileContent');

        $result = $service->downloadFile('/fm/496/cached-file.svg', 'tiles');

        $this->assertEquals('seeds/tiles/cached-file.svg', $result);
    }

    public function test_download_disabled_via_config_returns_null(): void
    {
        config(['seeding.media_download_enabled' => false]);

        $result = $this->service->downloadFile('/fm/496/test.svg', 'tiles');

        $this->assertNull($result);
    }

    public function test_download_file_returns_null_on_download_failure(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn(false);

        $result = $service->downloadFile('/fm/496/missing.svg', 'tiles');

        $this->assertNull($result);
    }

    public function test_extract_system_url_from_string(): void
    {
        $result = $this->service->extractSystemUrl('/fm/496/test-file.svg');

        $this->assertEquals('/fm/496/test-file.svg', $result);
    }

    public function test_extract_system_url_from_array(): void
    {
        $result = $this->service->extractSystemUrl(['systemurl' => '/fm/test']);

        $this->assertEquals('/fm/test', $result);
    }

    public function test_extract_system_url_from_null(): void
    {
        $result = $this->service->extractSystemUrl(null);

        $this->assertNull($result);
    }

    public function test_extract_system_url_from_empty_string(): void
    {
        $result = $this->service->extractSystemUrl('');

        $this->assertNull($result);
    }

    public function test_extract_system_url_from_array_without_systemurl_key(): void
    {
        $result = $this->service->extractSystemUrl(['other_key' => 'value']);

        $this->assertNull($result);
    }

    public function test_extract_filename_from_path(): void
    {
        $result = $this->service->extractFilename('/fm/496/test%20file.svg');

        $this->assertEquals('test%20file.svg', $result);
    }

    public function test_extract_filename_from_simple_path(): void
    {
        $result = $this->service->extractFilename('/fm/icon.png');

        $this->assertEquals('icon.png', $result);
    }

    public function test_extract_filename_from_deep_path(): void
    {
        $result = $this->service->extractFilename('/fm/123/456/deep-file.svg');

        $this->assertEquals('deep-file.svg', $result);
    }

    public function test_download_file_decodes_filename_for_local_storage(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $result = $service->downloadFile('/fm/496/SREG%20Dashboard.svg', 'tiles');

        $this->assertNotNull($result);
        $this->assertEquals('seeds/tiles/SREG Dashboard.svg', $result);
        Storage::disk('public')->assertExists('seeds/tiles/SREG Dashboard.svg');
    }

    public function test_download_asset_saves_to_public_disk(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $result = $service->downloadAsset('handlungsfelder/umwelt.svg', 'dimensions');

        $this->assertNotNull($result);
        $this->assertEquals('seeds/dimensions/umwelt.svg', $result);
        Storage::disk('public')->assertExists('seeds/dimensions/umwelt.svg');
    }

    public function test_download_asset_returns_null_when_disabled(): void
    {
        config(['seeding.media_download_enabled' => false]);

        $result = $this->service->downloadAsset('handlungsfelder/umwelt.svg', 'dimensions');

        $this->assertNull($result);
    }

    public function test_download_file_returns_null_for_empty_filename(): void
    {
        Storage::fake('public');

        $result = $this->service->downloadFile('/', 'tiles');

        $this->assertNull($result);
    }

    public function test_download_file_rejects_html_masquerading_as_svg(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn('<!DOCTYPE html><html><head><title>App</title></head><body></body></html>');

        $result = $service->downloadFile('/fm/496/broken-icon.svg', 'tiles');

        $this->assertNull($result);
        Storage::disk('public')->assertMissing('seeds/tiles/broken-icon.svg');
    }

    public function test_download_asset_rejects_html_masquerading_as_svg(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn('<!DOCTYPE html><html><body>SPA shell</body></html>');

        $result = $service->downloadAsset('handlungsfelder/mobilitaet_infrastruktur.svg', 'handlungsfelder');

        $this->assertNull($result);
        Storage::disk('public')->assertMissing('seeds/handlungsfelder/mobilitaet_infrastruktur.svg');
    }

    public function test_download_file_saves_non_svg_content_unchanged(): void
    {
        Storage::fake('public');

        $service = $this->getMockBuilder(MediaDownloadService::class)
            ->onlyMethods(['downloadFileContent'])
            ->getMock();

        // The SVG-content guard only applies to *.svg targets; other file types
        // (e.g. raster images) must still be saved with their raw bytes.
        $service->expects($this->once())
            ->method('downloadFileContent')
            ->willReturn('binary-png-bytes');

        $result = $service->downloadFile('/fm/496/photo.png', 'tiles');

        $this->assertNotNull($result);
        $this->assertEquals('seeds/tiles/photo.png', $result);
        Storage::disk('public')->assertExists('seeds/tiles/photo.png');
    }
}
