<?php

namespace App\Console\Commands;

use Database\Seeders\NavigationSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Console\Command;

/**
 * Artisan command to seed Fabricator Pages and Navigation from reference site.
 *
 * Usage:
 *   php artisan pages:seed
 *   php artisan pages:seed --dry-run
 */
class PagesSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pages:seed
                            {--dry-run : Run without persisting data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Fabricator Pages and Navigation from reference site';

    /**
     * Seed Fabricator pages and header/footer navigation, or display a dry-run summary.
     *
     * When the command is executed with the --dry-run option, a summary of the pages
     * and navigation that would be created is displayed and no data is persisted.
     *
     * @return int `Command::SUCCESS` on successful seeding or after a dry-run, `Command::FAILURE` if an error occurred.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode - no data will be persisted');
        }

        try {
            if ($dryRun) {
                $this->displayDryRunSummary();
                return Command::SUCCESS;
            }

            // Seed Pages
            $this->info('Seeding Fabricator Pages...');
            $pageSeeder = new PageSeeder();
            $pageSeeder->setCommand($this);
            $pageIdMap = $pageSeeder->run();

            $this->info("Seeded " . count($pageIdMap) . " pages");

            // Seed Navigation
            $this->info('Seeding Header and Footer Navigation...');
            $navigationSeeder = new NavigationSeeder();
            $navigationSeeder->setCommand($this);
            $navigationSeeder->run($pageIdMap);

            $this->info('Pages and Navigation seeded successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error seeding pages: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Print a summary of pages and navigation that would be created when running in dry-run mode.
     *
     * Outputs the list of pages (with paths) and header/footer navigation items that would be created or updated.
     */
    protected function displayDryRunSummary(): void
    {
        $this->info('Dry-run summary:');
        $this->line('Pages to be created/updated:');
        $this->line('  - Home (/)');
        $this->line('  - Kontakt/Contact (/kontakt /en/contact)');
        $this->line('  - Download (/download /en/download)');
        $this->line('  - Datenschutz/Privacy (/datenschutz /en/privacy)');
        $this->line('  - Impressum/Imprint (/impressum /en/imprint)');
        $this->line('');
        $this->line('Navigation items to be created:');
        $this->line('  Header: Download, Kontakt');
        $this->line('  Footer: Impressum, Datenschutz, regensburg.de, mein.regensburg.de');
    }
}
