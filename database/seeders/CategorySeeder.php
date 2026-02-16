<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Services\MediaDownloadService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seeder for all CategoryGroups and their Categories from dashboard.json.
 *
 * Seeds three groups:
 * - "fields" (Handlungsfelder) from parsed dashboard.json data
 * - "dimensions" (Handlungsdimensionen) with 3 static entries
 * - "sdg" (SDG-Ziele) with 17 static entries
 *
 * Supports idempotent upserts based on slug.
 */
class CategorySeeder extends Seeder
{
    protected MediaDownloadService $mediaDownloadService;

    /**
     * Static mapping of German Handlungsfeld titles to English translations.
     */
    protected array $handlungsfeldTitles = [
        'Partizipation und Teilhabe' => 'Participation and Inclusion',
        'Digitalisierung' => 'Digitalization',
        'Wissenschaft' => 'Science',
        'Arbeit und Wirtschaft' => 'Work and Economy',
        'Globale Verantwortung' => 'Global Responsibility',
        'Leben und Wohnen' => 'Living and Housing',
        'Mobilität und Infrastruktur' => 'Mobility and Infrastructure',
        'Umwelt und Ressourcenschutz' => 'Environment and Resource Protection',
        'Klimaschutz und Energie' => 'Climate Protection and Energy',
    ];

    /**
     * Static definition of the 3 Handlungsdimensionen.
     */
    protected array $dimensions = [
        'grün' => [
            'title' => ['de' => 'Grün', 'en' => 'Green'],
            'icon_path' => 'dimensionen/gruen.svg',
            'position' => 0,
            'color' => '#dcfce7',
        ],
        'gerecht' => [
            'title' => ['de' => 'Gerecht', 'en' => 'Just'],
            'icon_path' => 'dimensionen/gerecht.svg',
            'position' => 1,
            'color' => '#ffedd4',
        ],
        'produktiv' => [
            'title' => ['de' => 'Produktiv', 'en' => 'Productive'],
            'icon_path' => 'dimensionen/produktiv.svg',
            'position' => 2,
            'color' => '#dbeafe',
        ],
    ];

    /**
     * SDG title mapping (DE/EN) for all 17 goals.
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

    public function __construct(MediaDownloadService $mediaDownloadService)
    {
        $this->mediaDownloadService = $mediaDownloadService;
    }

    /**
     * Seed Handlungsfelder categories from parsed dashboard.json data.
     *
     * @param  Collection<int, \App\Services\ParsedCategory>  $categories  Parsed categories to seed.
     * @return array<string, int> Map of original category ID to database ID.
     */
    public function run(Collection $categories): array
    {
        $idMap = [];
        $position = 0;
        $group = $this->resolveFieldsGroup();

        if (! $group) {
            if ($this->command) {
                $this->command->error('Could not resolve Fields CategoryGroup. Aborting category seeding.');
            }

            return [];
        }

        foreach ($categories as $parsedCategory) {
            $slugDe = trim($parsedCategory->title);
            $slugEn = $this->handlungsfeldTitles[$slugDe] ?? null;

            $slugArray = [
                'de' => $slugDe,
                'en' => $slugEn,
            ];

            $iconSlug = $this->generateIconSlug($slugDe);
            $iconPath = null;

            if (config('seeding.media_download_enabled', true)) {
                $iconAssetPath = "handlungsfelder/{$iconSlug}.svg";
                $iconPath = $this->mediaDownloadService->downloadAsset($iconAssetPath, 'handlungsfelder');
                if ($iconPath && $this->command) {
                    $this->command->info("Downloaded category icon: {$iconPath}");
                }
            }

            $sourceHash = $this->calculateSourceHash($parsedCategory);

            $category = Category::whereJsonContains('slug->de', $slugDe)->first();

            $needsUpdate = false;

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group->id;
                $category->tenant_id = $group->tenant_id;
                $category->slug = $slugArray;
                if (! empty($iconPath)) {
                    $category->icon = json_encode(['de' => $iconPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $category->position = $position;
                $category->last_synced_at = now();
                $category->source_hash = $sourceHash;
                $category->save();
            } else {
                if ($category->getTranslation('slug', 'de') !== $slugDe) {
                    $category->setTranslation('slug', 'de', $slugDe);
                    $needsUpdate = true;
                }
                if ($slugEn && $category->getTranslation('slug', 'en') !== $slugEn) {
                    $category->setTranslation('slug', 'en', $slugEn);
                    $needsUpdate = true;
                }
                if (! empty($iconPath)) {
                    $newIconJson = json_encode(['de' => $iconPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (empty($category->icon) || $category->icon !== $newIconJson) {
                        $category->icon = $newIconJson;
                        $needsUpdate = true;
                    }
                }
                if ($category->position !== $position) {
                    $category->position = $position;
                    $needsUpdate = true;
                }
                if ($category->category_group_id !== $group->id) {
                    $category->category_group_id = $group->id;
                    $needsUpdate = true;
                }
                if ($category->tenant_id !== $group->tenant_id) {
                    $category->tenant_id = $group->tenant_id;
                    $needsUpdate = true;
                }
                if ($category->source_hash !== $sourceHash) {
                    $category->source_hash = $sourceHash;
                    $category->last_synced_at = now();
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $category->save();
                }
            }

            $idMap[$parsedCategory->id] = $category->id;

            if ($this->command) {
                $this->command->info("Category seeded: {$parsedCategory->title} (ID: {$category->id})");
            }
            $position++;
        }

        return $idMap;
    }

    /**
     * Seed the 3 static Handlungsdimensionen as Categories in the "dimensions" group.
     *
     * @return array<string, int> Map of dimension key to Category database ID.
     */
    public function seedDimensions(): array
    {
        $categoryIdMap = [];
        $group = $this->resolveDimensionsGroup();

        if (! $group) {
            if ($this->command) {
                $this->command->error('Could not resolve Dimensions CategoryGroup. Aborting dimension seeding.');
            }

            return [];
        }

        foreach ($this->dimensions as $key => $data) {
            $iconPath = null;
            if (config('seeding.media_download_enabled', true)) {
                $iconPath = $this->mediaDownloadService->downloadAsset($data['icon_path'], 'dimensions');
                if ($this->command && $iconPath) {
                    $this->command->info("Downloaded dimension icon: {$iconPath}");
                }
            }

            $category = Category::where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $data['title']['de'])
                ->first();

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group->id;
                $category->tenant_id = $group->tenant_id;
            }

            $category->slug = $data['title'];
            $category->icon = $iconPath;
            $category->color = $data['color'] ?? null;
            $category->position = $data['position'];
            $category->key = $key;
            $category->save();

            $categoryIdMap[$key] = $category->id;

            if ($this->command) {
                $this->command->info("Dimension category seeded: {$key} (ID: {$category->id})");
            }
        }

        return $categoryIdMap;
    }

    /**
     * Seed the 17 SDG-Ziele as Categories in the "sdg" group.
     *
     * @param  Collection<int, \App\Services\ParsedSDGZiel>  $sdgZiele  Parsed SDG entries (used for ID mapping).
     * @return array<int, int> Map of original parsed SDG ID to Category database ID.
     */
    public function seedSdgZiele(Collection $sdgZiele): array
    {
        $categoryIdMap = [];
        $group = $this->resolveSdgGroup();

        if (! $group) {
            if ($this->command) {
                $this->command->error('Could not resolve SDG CategoryGroup. Aborting SDG seeding.');
            }

            return [];
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

            $category = Category::where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $titles['de'])
                ->first();

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group->id;
                $category->tenant_id = $group->tenant_id;
            }

            $category->slug = $titles;
            $category->icon = json_encode($iconArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $category->position = $number;
            $category->save();

            $categoryIdMap[$parsedSDG->id] = $category->id;

            if ($this->command) {
                $this->command->info("SDG category seeded: {$titles['de']} (Number: {$number}, ID: {$category->id})");
            }
        }

        return $categoryIdMap;
    }

    protected function resolveFieldsGroup(): ?CategoryGroup
    {
        $tenantId = $this->resolveTenantId();

        return CategoryGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'fields'],
            [
                'title' => ['de' => 'Handlungsfelder', 'en' => 'Action Fields'],
                'position' => 0,
                'is_filterable' => true,
                'is_color_source' => false,
                'selection_type' => 'multi',
            ]
        );
    }

    protected function resolveDimensionsGroup(): ?CategoryGroup
    {
        $tenantId = $this->resolveTenantId();

        return CategoryGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'dimensions'],
            [
                'title' => ['de' => 'Handlungsdimensionen', 'en' => 'Action Dimensions'],
                'position' => 1,
                'is_filterable' => true,
                'is_color_source' => true,
                'selection_type' => 'single',
            ]
        );
    }

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

    protected function resolveTenantId(): ?int
    {
        if (class_exists(\Filament\Facades\Filament::class) && \Filament\Facades\Filament::getTenant()) {
            return \Filament\Facades\Filament::getTenant()->id;
        }

        return Tenant::where('slug', 'default')->value('id')
            ?? Tenant::where('slug', 'stadt-regensburg')->value('id')
            ?? Tenant::first()?->id;
    }

    protected function generateSlug(string $title): string
    {
        return Str::slug($title);
    }

    protected function generateIconSlug(string $title): string
    {
        $title = trim($title);
        $slug = mb_strtolower($title, 'UTF-8');
        $slug = preg_replace('/\s+und\s+/u', '_', $slug);
        $slug = preg_replace('/[^a-z0-9äöüß]+/u', '_', $slug);
        $slug = trim($slug, '_');

        return $slug;
    }

    protected function calculateSourceHash(\App\Services\ParsedCategory $category): string
    {
        $dataToHash = [
            'id' => $category->id,
            'title' => $category->title,
        ];

        return hash('sha256', json_encode($dataToHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
