<?php

namespace App\Console\Commands;

use App\Models\CategoryGroup;
use App\Services\DashboardJsonParser;
use App\Services\MediaDownloadService;
use App\Settings\BrandingSettings;
use Database\Seeders\CategorySeeder;
use Database\Seeders\MetricSeeder;
use Database\Seeders\TileSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to seed Tiles and Categories from dashboard.json.
 *
 * Usage:
 *   php artisan dashboard:seed --path=path/to/dashboard.json
 *   php artisan dashboard:seed --path=path/to/dashboard.json --dry-run
 */
class DashboardSeedCommand extends Command
{
    protected $signature = 'dashboard:seed
                            {--path= : Path to dashboard.json file}
                            {--url= : URL to fetch dashboard.json from}
                            {--only= : Seed only a subset (categories|handlungsfelder)}
                            {--dry-run : Run without persisting data}';

    protected $description = 'Seed Tiles and Categories from Regensburg dashboard.json';

    public function handle(): int
    {
        if ($url = $this->option('url')) {
            $this->info("Fetching dashboard.json from {$url}...");

            $response = Http::timeout(30)->get($url);

            if ($response->failed()) {
                $this->error("Failed to fetch dashboard.json from {$url} (HTTP {$response->status()})");

                return Command::FAILURE;
            }

            $storagePath = config('seeding.default_json_path');
            $directory = dirname($storagePath);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($storagePath, $response->body());
            $this->info("Saved dashboard.json to {$storagePath}");
        }

        $jsonPath = $this->option('path') ?? config('seeding.default_json_path');
        $dryRun = $this->option('dry-run');
        $only = $this->normalizeOnlyOption($this->option('only'));

        if ($this->option('only') && $only === null) {
            $this->error("Invalid --only value: '{$this->option('only')}'. Valid values: categories, handlungsfelder");

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode - no data will be persisted');
        }

        try {
            $parser = new DashboardJsonParser;
            $parsed = $parser->parse($jsonPath);

            $this->info("Parsed {$parsed['categories']->count()} categories, {$parsed['tiles']->count()} tiles, {$parsed['metrics']->count()} metrics, and {$parsed['sdg_ziele']->count()} SDG-Ziele");

            if ($dryRun) {
                $this->displayDryRunSummary($parsed);

                return Command::SUCCESS;
            }

            return DB::transaction(function () use ($parsed, $only) {
                $mediaDownloadService = new MediaDownloadService;

                // 1. Seed all category groups via the unified CategorySeeder
                $categorySeeder = new CategorySeeder($mediaDownloadService);
                $categorySeeder->setCommand($this);

                // 1a. Seed Handlungsfelder (from parsed dashboard.json)
                $categoryIdMap = $categorySeeder->run($parsed['categories']);

                if ($only === 'categories') {
                    $this->info('Category seeding completed successfully!');

                    return Command::SUCCESS;
                }

                // 1b. Seed Handlungsdimensionen (3 static entries)
                $dimensionCategoryMap = $categorySeeder->seedDimensions();

                // 1c. Seed SDG-Ziele (17 entries from parsed data)
                $sdgCategoryMap = $categorySeeder->seedSdgZiele($parsed['sdg_ziele']);

                // 1d. Set default branding tile settings
                $this->seedTileBrandingDefaults();

                // 2. Seed Tiles (with category relations from all groups)
                $tileSeeder = new TileSeeder;
                $tileSeeder->setCommand($this);
                $tileIdMap = $tileSeeder->run(
                    $parsed['tiles'],
                    $categoryIdMap,
                    $dimensionCategoryMap,
                    $sdgCategoryMap,
                );

                // Build tile to metric mapping from parsed tiles
                $tileMetricMapping = [];
                foreach ($parsed['tiles'] as $parsedTile) {
                    if (! empty($parsedTile->metricIds)) {
                        $tileMetricMapping[$parsedTile->id] = $parsedTile->metricIds;
                    }
                }

                // Seed metrics if there are any
                if ($parsed['metrics']->isNotEmpty() && ! empty($tileMetricMapping)) {
                    $metricSeeder = new MetricSeeder;
                    $metricSeeder->setCommand($this);
                    $metricSeeder->run($parsed['metrics'], $tileIdMap, $tileMetricMapping);
                }

                $this->info('Seeding completed successfully!');

                return Command::SUCCESS;
            });
        } catch (\Exception $e) {
            Log::error('Dashboard seeding failed', ['exception' => $e]);

            $this->error('Seeding failed. Check logs for details.');

            return Command::FAILURE;
        }
    }

    protected function seedTileBrandingDefaults(): void
    {
        $settings = app(BrandingSettings::class);
        $defaultTenant = \App\Models\Tenant::where('slug', 'default')->first();
        $tenantId = $defaultTenant?->id;

        if ($settings->tile_color_source_group_id === null && $tenantId) {
            $dimensionsGroup = CategoryGroup::where('key', 'dimensions')
                ->where('tenant_id', $tenantId)
                ->first();
            if ($dimensionsGroup) {
                $settings->tile_color_source_group_id = $dimensionsGroup->id;
                $this->info("Set tile color source to: {$dimensionsGroup->getTranslation('title', 'de')}");
            }
        }

        if ($settings->tile_background_category_group_id === null && $tenantId) {
            $sdgGroup = CategoryGroup::where('key', 'sdg')
                ->where('tenant_id', $tenantId)
                ->first();
            if ($sdgGroup) {
                $settings->tile_background_category_group_id = $sdgGroup->id;
                $this->info("Set tile background category group to: {$sdgGroup->getTranslation('title', 'de')}");
            }
        }

        $settings->save();
    }

    protected function displayDryRunSummary(array $parsed): void
    {
        $this->info('=== DRY-RUN SUMMARY ===');
        $this->info("Categories to seed: {$parsed['categories']->count()}");
        $this->info('Handlungsdimensionen to seed: 3 (static)');
        $this->info("SDG-Ziele to seed: {$parsed['sdg_ziele']->count()}");
        $this->info("Tiles to seed: {$parsed['tiles']->count()}");
        $this->info("Relationships to create: {$parsed['links']->count()}");
        $this->info("Metrics to seed: {$parsed['metrics']->count()}");

        if ($this->option('verbose')) {
            $this->newLine();
            $this->info('Categories:');
            foreach ($parsed['categories']->take(5) as $category) {
                $this->line("  - {$category->title}");
            }

            $this->newLine();
            $this->info('Tiles (first 5):');
            foreach ($parsed['tiles']->take(5) as $tile) {
                $this->line("  - {$tile->title}");
            }
        }
    }

    protected function normalizeOnlyOption(?string $only): ?string
    {
        if (! $only) {
            return null;
        }

        $only = strtolower(trim($only));

        if (in_array($only, ['categories', 'handlungsfelder'], true)) {
            return 'categories';
        }

        return null;
    }
}
