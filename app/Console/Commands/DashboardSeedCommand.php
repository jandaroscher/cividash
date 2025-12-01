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
                            {--dry-run : Run without persisting data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Tiles and Categories from Regensburg dashboard.json';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jsonPath = $this->option('path') ?? config('seeding.default_json_path');
        $dryRun = $this->option('dry-run');

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

            return DB::transaction(function () use ($parsed) {
                $mediaDownloadService = new MediaDownloadService();

                // 1. Seed Handlungsfelder (Categories)
                $categorySeeder = new CategorySeeder($mediaDownloadService);
                $categorySeeder->setCommand($this);
                $categoryIdMap = $categorySeeder->run($parsed['categories']);

                // 2. Seed Handlungsdimensionen (statisch, mit Mapping zu Handlungsfeldern)
                $handlungsdimensionSeeder = new HandlungsdimensionSeeder($mediaDownloadService);
                $handlungsdimensionSeeder->setCommand($this);
                $handlungsdimensionIdMap = $handlungsdimensionSeeder->run($categoryIdMap);

                // 3. Seed SDG-Ziele
                $sdgZielSeeder = new SDGZielSeeder($mediaDownloadService);
                $sdgZielSeeder->setCommand($this);
                $sdgZielIdMap = $sdgZielSeeder->run($parsed['sdg_ziele']);

                // 4. Seed Tiles (mit Handlungsdimensionen und SDG-Zielen)
                $tileSeeder = new TileSeeder;
                $tileSeeder->setCommand($this);
                $tileIdMap = $tileSeeder->run($parsed['tiles'], $categoryIdMap, $handlungsdimensionIdMap, $sdgZielIdMap);

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
     * Display summary for dry-run mode.
     *
     * @param array $parsed
     * @return void
     */
    protected function displayDryRunSummary(array $parsed): void
    {
        $this->info('=== DRY-RUN SUMMARY ===');
        $this->info("Categories to seed: {$parsed['categories']->count()}");
        $this->info("Handlungsdimensionen to seed: 3 (static)");
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
}

