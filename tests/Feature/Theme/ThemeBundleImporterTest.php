<?php

namespace Tests\Feature\Theme;

use App\Models\Theme;
use App\Services\ThemeBundleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ThemeBundleImporterTest extends TestCase
{
    use RefreshDatabase;

    private array $zipPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->zipPaths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'theme-bundle-').'.zip';
        $this->zipPaths[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    public function test_imports_valid_bundle_with_assets_and_rewrites_paths(): void
    {
        Storage::fake('public');

        $themeJson = json_encode([
            'name' => 'Demo City',
            'slug' => 'demo-city',
            'settings' => [
                'branding' => [
                    'primary_color' => '#0d47a1',
                    'logo_url' => 'assets/logo.png',
                ],
            ],
        ]);

        $zipPath = $this->makeZip([
            'theme.json' => $themeJson,
            'assets/logo.png' => 'fake-png-bytes',
        ]);

        $importer = new ThemeBundleImporter;
        $theme = $importer->import($zipPath);

        $this->assertInstanceOf(Theme::class, $theme);
        $this->assertSame('Demo City', $theme->name);
        $this->assertSame('demo-city', $theme->slug);

        $logoUrl = $theme->settings['branding']['logo_url'];
        $this->assertStringNotContainsString('assets/logo.png', $logoUrl);
        $this->assertStringContainsString('themes/demo-city/logo.png', $logoUrl);

        Storage::disk('public')->assertExists('themes/demo-city/logo.png');
    }

    public function test_updates_existing_theme_by_slug(): void
    {
        Storage::fake('public');

        Theme::create(['name' => 'Old Name', 'slug' => 'demo-city', 'settings' => null]);

        $zipPath = $this->makeZip([
            'theme.json' => json_encode([
                'name' => 'New Name',
                'slug' => 'demo-city',
                'settings' => ['branding' => ['primary_color' => '#fff']],
            ]),
        ]);

        $theme = (new ThemeBundleImporter)->import($zipPath);

        $this->assertSame(1, Theme::where('slug', 'demo-city')->count());
        $this->assertSame('New Name', $theme->fresh()->name);
    }

    public function test_missing_theme_json_is_rejected(): void
    {
        $zipPath = $this->makeZip(['readme.txt' => 'nothing here']);

        $this->expectException(RuntimeException::class);
        (new ThemeBundleImporter)->import($zipPath);
    }

    public function test_invalid_settings_structure_is_rejected(): void
    {
        $zipPath = $this->makeZip([
            'theme.json' => json_encode([
                'name' => 'Broken',
                'slug' => 'broken',
                'settings' => ['branding' => 'not-an-object'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        (new ThemeBundleImporter)->import($zipPath);

        $this->assertDatabaseMissing('themes', ['slug' => 'broken']);
    }

    public function test_unknown_settings_group_is_rejected(): void
    {
        $zipPath = $this->makeZip([
            'theme.json' => json_encode([
                'name' => 'Broken',
                'slug' => 'broken',
                'settings' => ['not_a_group' => ['foo' => 'bar']],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        (new ThemeBundleImporter)->import($zipPath);
    }

    public function test_zip_slip_path_is_rejected(): void
    {
        Storage::fake('public');

        $zipPath = $this->makeZip([
            'theme.json' => json_encode([
                'name' => 'Evil',
                'slug' => 'evil',
                'settings' => null,
            ]),
            'assets/../../etc/passwd' => 'malicious',
        ]);

        $this->expectException(RuntimeException::class);
        (new ThemeBundleImporter)->import($zipPath);
    }

    public function test_forbidden_asset_extension_is_rejected(): void
    {
        Storage::fake('public');

        $zipPath = $this->makeZip([
            'theme.json' => json_encode([
                'name' => 'Evil',
                'slug' => 'evil',
                'settings' => null,
            ]),
            'assets/script.php' => '<?php echo "hi"; ?>',
        ]);

        $this->expectException(RuntimeException::class);
        (new ThemeBundleImporter)->import($zipPath);
    }

    public function test_slug_with_path_traversal_is_rejected(): void
    {
        Storage::fake('public');

        $zipPath = $this->makeZip([
            'theme.json' => json_encode(['name' => 'Evil', 'slug' => '../evil', 'settings' => null]),
        ]);

        $this->expectException(RuntimeException::class);
        (new ThemeBundleImporter)->import($zipPath);
    }

    public function test_rejected_bundle_keeps_existing_theme_assets(): void
    {
        Storage::fake('public');

        (new ThemeBundleImporter)->import($this->makeZip([
            'theme.json' => json_encode(['name' => 'Keep', 'slug' => 'keep', 'settings' => null]),
            'assets/logo.svg' => '<svg/>',
        ]));
        Storage::disk('public')->assertExists('themes/keep/logo.svg');

        try {
            (new ThemeBundleImporter)->import($this->makeZip([
                'theme.json' => json_encode(['name' => 'Keep', 'slug' => 'keep', 'settings' => null]),
                'assets/logo.svg' => '<svg/>',
                'assets/script.php' => '<?php',
            ]));
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('public')->assertExists('themes/keep/logo.svg');
    }
}
