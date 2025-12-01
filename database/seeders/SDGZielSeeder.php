<?php

namespace Database\Seeders;

use App\Models\SDGZiel;
use App\Services\DashboardJsonParser;
use App\Services\MediaDownloadService;
use Illuminate\Database\Seeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Seeder for SDG-Ziele (SDG Goals).
 *
 * Seeds SDG goals from dashboard.json with full titles and icons.
 */
class SDGZielSeeder extends Seeder
{
    protected MediaDownloadService $mediaDownloadService;

    public function __construct(MediaDownloadService $mediaDownloadService)
    {
        $this->mediaDownloadService = $mediaDownloadService;
    }

    /**
     * Set the command instance for logging.
     */
    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    /**
     * SDG title mapping (DE/EN).
     *
     * @var array<int, array{de: string, en: string}>
     */
    protected array $sdgTitles = [
        1 => ['de' => 'KEINE ARMUT', 'en' => 'NO POVERTY'],
        2 => ['de' => 'KEIN HUNGER', 'en' => 'ZERO HUNGER'],
        3 => ['de' => 'GESUNDHEIT UND WOHLERGEHEN', 'en' => 'GOOD HEALTH AND WELL-BEING'],
        4 => ['de' => 'HOCHWERTIGE BILDUNG', 'en' => 'QUALITY EDUCATION'],
        5 => ['de' => 'GESCHLECHTERGLEICHHEIT', 'en' => 'GENDER EQUALITY'],
        6 => ['de' => 'SAUBERES WASSER UND SANITÄREINRICHTUNGEN', 'en' => 'CLEAN WATER AND SANITATION'],
        7 => ['de' => 'BEZAHLBARE UND SAUBERE ENERGIE', 'en' => 'AFFORDABLE AND CLEAN ENERGY'],
        8 => ['de' => 'MENSCHWÜRDIGE ARBEIT UND WIRTSCHAFTSWACHSTUM', 'en' => 'DECENT WORK AND ECONOMIC GROWTH'],
        9 => ['de' => 'INDUSTRIE, INNOVATION UND INFRASTRUKTUR', 'en' => 'INDUSTRY, INNOVATION AND INFRASTRUCTURE'],
        10 => ['de' => 'WENIGER UNGLEICHHEITEN', 'en' => 'REDUCED INEQUALITIES'],
        11 => ['de' => 'NACHHALTIGE STÄDTE UND GEMEINDEN', 'en' => 'SUSTAINABLE CITIES AND COMMUNITIES'],
        12 => ['de' => 'NACHHALTIGER KONSUM UND PRODUKTION', 'en' => 'RESPONSIBLE CONSUMPTION AND PRODUCTION'],
        13 => ['de' => 'MASSNAHMEN ZUM KLIMASCHUTZ', 'en' => 'CLIMATE ACTION'],
        14 => ['de' => 'LEBEN UNTER WASSER', 'en' => 'LIFE BELOW WATER'],
        15 => ['de' => 'LEBEN AN LAND', 'en' => 'LIFE ON LAND'],
        16 => ['de' => 'FRIEDEN, GERECHTIGKEIT UND STARKE INSTITUTIONEN', 'en' => 'PEACE, JUSTICE AND STRONG INSTITUTIONS'],
        17 => ['de' => 'PARTNERSCHAFTEN ZUR ERREICHUNG DER ZIELE', 'en' => 'PARTNERSHIPS FOR THE GOALS'],
    ];

    /**
     * Run the database seeds.
     *
     * @param Collection<int, \App\Services\ParsedSDGZiel> $sdgZiele
     * @return array<int, int> Map of original SDG ID to database ID
     */
    public function run(Collection $sdgZiele): array
    {
        $idMap = [];

        foreach ($sdgZiele as $parsedSDG) {
            if ($parsedSDG->number === null || $parsedSDG->number < 1 || $parsedSDG->number > 17) {
                if ($this->command) {
                    $this->command->warn("Skipping SDG with invalid number: {$parsedSDG->number} (ID: {$parsedSDG->id})");
                }
                continue;
            }

            $number = $parsedSDG->number;
            $titles = $this->sdgTitles[$number] ?? null;

            if (! $titles) {
                if ($this->command) {
                    $this->command->warn("No title mapping found for SDG number: {$number}");
                }
                continue;
            }

            // Download icons (DE and EN) and save as local paths
            $numberPadded = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $iconDePath = null;
            $iconEnPath = null;
            
            if (config('seeding.media_download_enabled', true)) {
                $iconDeUrl = "sdg/SDG-icon-DE-{$numberPadded}.svg";
                $iconEnUrl = "sdg/SDG-icon-EN-{$numberPadded}.svg";
                $iconDePath = $this->mediaDownloadService->downloadAsset($iconDeUrl, 'sdg');
                $iconEnPath = $this->mediaDownloadService->downloadAsset($iconEnUrl, 'sdg');
                if ($this->command && ($iconDePath || $iconEnPath)) {
                    $this->command->info("Downloaded SDG icons: DE={$iconDePath}, EN={$iconEnPath}");
                }
            }

            $iconArray = [
                'de' => $iconDePath,
                'en' => $iconEnPath,
            ];

            $sdgZiel = SDGZiel::where('number', $number)->first();

            if (! $sdgZiel) {
                $sdgZiel = new SDGZiel();
                $sdgZiel->number = $number;
                $sdgZiel->title = $titles;
                $sdgZiel->icon = $iconArray; // Store as translatable array
                $sdgZiel->position = $number; // Use number as position
                $sdgZiel->save();
            } else {
                // Update if needed
                $needsUpdate = false;
                if ($sdgZiel->getTranslation('title', 'de') !== $titles['de']) {
                    $sdgZiel->setTranslation('title', 'de', $titles['de']);
                    $needsUpdate = true;
                }
                if ($sdgZiel->getTranslation('title', 'en') !== $titles['en']) {
                    $sdgZiel->setTranslation('title', 'en', $titles['en']);
                    $needsUpdate = true;
                }
                // Check icon translations
                if ($sdgZiel->getTranslation('icon', 'de') !== $iconArray['de']) {
                    $sdgZiel->setTranslation('icon', 'de', $iconArray['de']);
                    $needsUpdate = true;
                }
                if ($sdgZiel->getTranslation('icon', 'en') !== $iconArray['en']) {
                    $sdgZiel->setTranslation('icon', 'en', $iconArray['en']);
                    $needsUpdate = true;
                }
                if ($sdgZiel->position !== $number) {
                    $sdgZiel->position = $number;
                    $needsUpdate = true;
                }
                if ($needsUpdate) {
                    $sdgZiel->save();
                }
            }

            $idMap[$parsedSDG->id] = $sdgZiel->id;

            if ($this->command) {
                $this->command->info("SDG-Ziel seeded: {$titles['de']} (Number: {$number}, ID: {$sdgZiel->id})");
            }
        }

        return $idMap;
    }
}
