<?php

namespace App\Console\Commands;

use App\Services\DashboardJsonParser;
use App\Services\MediaDownloadService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\HandlungsdimensionSeeder;
use Database\Seeders\MetricSeeder;
use Database\Seeders\SDGZielSeeder;
use Database\Seeders\TileSeeder;
use Illuminate\Console\Command;
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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:seed
                            {--path= : Path to dashboard.json file}
                            {--only= : Seed only a subset (categories|handlungsfelder)}
                            {--dry-run : Run without persisting data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Tiles and Categories from Regensburg dashboard.json';

    /**
     * Seed dashboard data from a dashboard.json file into the database, optionally performing a dry-run or seeding only a subset.
     *
     * Reads the configured or provided JSON path, parses dashboard entities (categories, tiles, metrics, SDG-Ziele), and executes the seeding flow.
     * When `--dry-run` is used, outputs a summary without persisting data. When `--only=categories` is used, seeds categories and exits early.
     *
     * @return int Command::SUCCESS on success, Command::FAILURE on error.
     */
    public function handle(): int
    {
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

                // 1. Seed Handlungsfelder (Categories)
                $categorySeeder = new CategorySeeder($mediaDownloadService);
                $categorySeeder->setCommand($this);
                $categoryIdMap = $categorySeeder->run($parsed['categories']);

                if ($only === 'categories') {
                    $this->info('Category seeding completed successfully!');

                    return Command::SUCCESS;
                }

                // 2. Seed Handlungsdimensionen (statisch, mit Mapping zu Handlungsfeldern)
                $handlungsdimensionSeeder = new HandlungsdimensionSeeder($mediaDownloadService);
                $handlungsdimensionSeeder->setCommand($this);
                $dimensionMaps = $handlungsdimensionSeeder->run($categoryIdMap);
                $handlungsdimensionIdMap = $dimensionMaps['dimension_ids'] ?? [];
                $handlungsdimensionCategoryMap = $dimensionMaps['category_ids'] ?? [];

                // 3. Seed SDG-Ziele
                $sdgZielSeeder = new SDGZielSeeder($mediaDownloadService);
                $sdgZielSeeder->setCommand($this);
                $sdgMaps = $sdgZielSeeder->run($parsed['sdg_ziele']);
                $sdgZielIdMap = $sdgMaps['sdg_ids'] ?? [];
                $sdgZielCategoryMap = $sdgMaps['category_ids'] ?? [];

                // 4. Seed Tiles (mit Handlungsdimensionen und SDG-Zielen)
                $tileSeeder = new TileSeeder;
                $tileSeeder->setCommand($this);
                $tileIdMap = $tileSeeder->run(
                    $parsed['tiles'],
                    $categoryIdMap,
                    $handlungsdimensionCategoryMap,
                    $sdgZielCategoryMap,
                    $handlungsdimensionIdMap,
                    $sdgZielIdMap
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
            // Log full exception details (including stack trace) for debugging
            Log::error('Dashboard seeding failed', ['exception' => $e]);

            // Present concise, non-sensitive error message to user
            $this->error('Seeding failed. Check logs for details.');

            return Command::FAILURE;
        }
    }

    /**
     * Output a concise summary of what would be seeded when running in dry-run mode.
     *
     * Displays counts for categories, handlungsdimensionen (fixed as 3), SDG goals, tiles,
     * relationship links, and metrics; when verbose, lists the first five category and tile titles.
     *
     * @param  array  $parsed  Parsed dashboard data containing at least the keys:
     *                         - 'categories' (collection with count() and items having `title`),
     *                         - 'sdg_ziele' (collection),
     *                         - 'tiles' (collection with items having `title`),
     *                         - 'links' (collection),
     *                         - 'metrics' (collection).
     */
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

    /**
     * Normalize the CLI --only option to a canonical value.
     *
     * Trims whitespace and compares case-insensitively. Accepts "categories" or
     * "handlungsfelder" and maps both to the canonical value "categories".
     *
     * @param  string|null  $only  The raw --only option value.
     * @return string|null `'categories' if the option corresponds to categories or handlungsfelder, null otherwise.`
     */
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
