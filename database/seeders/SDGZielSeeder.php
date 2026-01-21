<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\SDGZiel;
use App\Models\Tenant;
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
     * Seeds SDG goals and their corresponding categories from parsed SDG entries.
     *
     * For each parsed entry, validates the SDG number, creates or updates the SDGZiel record with localized titles, icons (downloads assets when enabled), and position, and creates or updates a Category linked to the SDG group. Builds and returns mappings from the original parsed SDG IDs to the created/updated SDG and Category database IDs.
     *
     * @param Collection<int, \App\Services\ParsedSDGZiel> $sdgZiele Collection of parsed SDG entries to seed.
     * @return array<string, array<int,int>> Associative array with keys 'sdg_ids' and 'category_ids', each mapping original SDG IDs to database IDs.
     */
    public function run(Collection $sdgZiele): array
    {
        $sdgIdMap = [];
        $categoryIdMap = [];
        $group = $this->resolveSdgGroup();

        if (! $group) {
            if ($this->command) {
                $this->command->error('Could not resolve SDG CategoryGroup. Aborting SDG seeding.');
            }

            return ['sdg_ids' => [], 'category_ids' => []];
        }

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

            $category = Category::where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $titles['de'])
                ->first();

            if (! $category) {
                $category = new Category();
                $category->category_group_id = $group->id;
                $category->tenant_id = $sdgZiel->tenant_id;
            }

            $category->slug = $titles;
            $category->icon = json_encode($iconArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $category->position = $number;
            $category->save();

            $sdgIdMap[$parsedSDG->id] = $sdgZiel->id;
            $categoryIdMap[$parsedSDG->id] = $category->id;

            if ($this->command) {
                $this->command->info("SDG-Ziel seeded: {$titles['de']} (Number: {$number}, ID: {$sdgZiel->id})");
            }
        }

        return [
            'sdg_ids' => $sdgIdMap,
            'category_ids' => $categoryIdMap,
        ];
    }

    /**
     * Ensure a CategoryGroup with key "sdg" exists for the resolved tenant and return it.
     *
     * The group is created or updated with localized titles ("SDG-Ziele" / "SDG Goals"), position 2,
     * is_filterable = true, is_color_source = false, and selection_type = "multi".
     *
     * @return CategoryGroup|null The created or updated CategoryGroup for the tenant, or `null` if a group cannot be resolved.
     */
    protected function resolveSdgGroup(): ?CategoryGroup
    {
        $tenantId = $this->resolveTenantId();

        return CategoryGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'sdg'],
            [
                'title' => ['de' => 'SDG-Ziele', 'en' => 'SDG Goals'],
                'position' => 2,
                'is_filterable' => true,
                'is_color_source' => false,
                'selection_type' => 'multi',
            ]
        );
    }

    /**
     * Resolve the current tenant's ID for seeding context.
     *
     * Checks for an active Filament tenant and returns its ID; if none is active,
     * falls back to: 'default' slug -> 'stadt-regensburg' slug -> first available tenant.
     *
     * @return int|null The resolved tenant ID, or `null` when no tenant is available.
     */
    protected function resolveTenantId(): ?int
    {
        if (class_exists(\Filament\Facades\Filament::class) && \Filament\Facades\Filament::getTenant()) {
            return \Filament\Facades\Filament::getTenant()->id;
        }

        return Tenant::where('slug', 'default')->value('id')
            ?? Tenant::where('slug', 'stadt-regensburg')->value('id')
            ?? Tenant::first()?->id;
    }
}