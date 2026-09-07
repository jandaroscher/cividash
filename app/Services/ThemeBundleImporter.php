<?php

namespace App\Services;

use App\Models\Theme;
use App\Settings\BrandingSettings;
use App\Settings\ContentSettings;
use App\Settings\DashboardSettings;
use App\Settings\GeneralSettings;
use App\Settings\IntegrationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Imports a theme bundle ZIP (theme.json + optional assets/ folder) into a
 * Theme record. Assets are unpacked to storage/app/public/themes/{slug}/ and
 * any settings value starting with "assets/" is rewritten to the public URL.
 */
class ThemeBundleImporter
{
    private const MAX_TOTAL_ASSET_BYTES = 25 * 1024 * 1024;

    private const ALLOWED_ASSET_EXTENSIONS = ['png', 'svg', 'jpg', 'jpeg', 'webp', 'woff2', 'woff', 'ico'];

    public function import(UploadedFile|string $zip): Theme
    {
        $path = $zip instanceof UploadedFile ? $zip->getRealPath() : $zip;

        $archive = new ZipArchive;

        if ($path === false || $archive->open($path) !== true) {
            throw new RuntimeException(__('filament.resources.theme.import_invalid_zip'));
        }

        try {
            $themeJson = $archive->getFromName('theme.json');

            if ($themeJson === false) {
                throw new RuntimeException(__('filament.resources.theme.import_missing_theme_json'));
            }

            $data = json_decode($themeJson, true);

            if (! is_array($data)) {
                throw new RuntimeException(__('filament.resources.theme.import_invalid_theme_json'));
            }

            $name = $data['name'] ?? null;
            $slug = $data['slug'] ?? null;
            $settings = $data['settings'] ?? null;

            if (! is_string($name) || $name === '' || ! is_string($slug) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                throw new RuntimeException(__('filament.resources.theme.import_invalid_theme_json'));
            }

            $this->validateSettingsStructure($settings);

            $assetMap = $this->extractAssets($archive, $slug);

            $settings = $this->rewriteAssetPaths($settings, $assetMap);

            return Theme::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'settings' => $settings,
            ]);
        } finally {
            $archive->close();
        }
    }

    private function validateSettingsStructure(mixed $settings): void
    {
        if ($settings === null) {
            return;
        }

        if (! is_array($settings) || array_is_list($settings)) {
            throw new RuntimeException(__('filament.resources.theme.settings_invalid_structure'));
        }

        $knownGroups = [
            GeneralSettings::group(),
            ContentSettings::group(),
            DashboardSettings::group(),
            BrandingSettings::group(),
            IntegrationSettings::group(),
        ];

        foreach ($settings as $group => $groupSettings) {
            if (! in_array($group, $knownGroups, true)) {
                throw new RuntimeException(__('filament.resources.theme.settings_unknown_group', ['group' => $group]));
            }

            if (! is_array($groupSettings) || array_is_list($groupSettings)) {
                throw new RuntimeException(__('filament.resources.theme.settings_invalid_structure'));
            }
        }
    }

    /**
     * Unpack the assets/ folder to storage/app/public/themes/{slug}/.
     *
     * @return array<string, string> map of zip entry name ("assets/foo.png") to its public URL.
     */
    private function extractAssets(ZipArchive $archive, string $slug): array
    {
        $destDir = "themes/{$slug}";

        // Pass 1: validate every asset entry before touching storage, so a
        // rejected bundle never destroys the assets of an existing theme.
        $entries = [];
        $totalBytes = 0;

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);

            if ($name === false || ! str_starts_with($name, 'assets/') || str_ends_with($name, '/')) {
                continue;
            }

            $this->assertSafePath($name);

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, self::ALLOWED_ASSET_EXTENSIONS, true)) {
                throw new RuntimeException(__('filament.resources.theme.import_forbidden_extension', ['file' => $name]));
            }

            $stat = $archive->statIndex($i);
            $totalBytes += $stat['size'] ?? 0;

            if ($totalBytes > self::MAX_TOTAL_ASSET_BYTES) {
                throw new RuntimeException(__('filament.resources.theme.import_assets_too_large'));
            }

            $entries[$name] = $i;
        }

        // Pass 2: replace the theme's asset directory.
        Storage::disk('public')->deleteDirectory($destDir);

        $map = [];

        foreach ($entries as $name => $index) {
            $contents = $archive->getFromIndex($index);

            if ($contents === false) {
                throw new RuntimeException(__('filament.resources.theme.import_invalid_zip'));
            }

            $storagePath = "{$destDir}/".substr($name, strlen('assets/'));
            Storage::disk('public')->put($storagePath, $contents);

            $map[$name] = Storage::disk('public')->url($storagePath);
        }

        return $map;
    }

    /**
     * Zip-slip guard: reject traversal ("..") and absolute paths before any
     * file is written to storage.
     */
    private function assertSafePath(string $name): void
    {
        if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $name)) {
            throw new RuntimeException(__('filament.resources.theme.import_unsafe_path', ['file' => $name]));
        }
    }

    private function rewriteAssetPaths(?array $settings, array $assetMap): ?array
    {
        if ($settings === null || $assetMap === []) {
            return $settings;
        }

        array_walk_recursive($settings, function (&$value) use ($assetMap): void {
            if (is_string($value) && str_starts_with($value, 'assets/') && isset($assetMap[$value])) {
                $value = $assetMap[$value];
            }
        });

        return $settings;
    }
}
