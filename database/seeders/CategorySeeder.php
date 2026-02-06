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
 * Seeder for Categories from dashboard.json.
 *
 * Seeds categories (handlungsfelder) from the Regensburg dashboard.json file.
 * Supports idempotent upserts based on slug.
 */
class CategorySeeder extends Seeder
{
    protected MediaDownloadService $mediaDownloadService;

    /**
     * Static mapping of German Handlungsfeld titles to English translations.
     *
     * @var array<string, string>
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

    public function __construct(MediaDownloadService $mediaDownloadService)
    {
        $this->mediaDownloadService = $mediaDownloadService;
    }

    /**
     * Seed categories from parsed input by creating or updating database records.
     *
     * Creates new or updates existing Category records (matched by the German slug),
     * assigns them to the resolved "fields" category group, optionally downloads and
     * attaches icon assets when enabled, updates position, source hash, and
     * last_synced_at, and returns a mapping of original parsed category IDs to
     * database record IDs.
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
            $slugDe = trim($parsedCategory->title); // Trim whitespace
            $slugEn = $this->handlungsfeldTitles[$slugDe] ?? null;

            $slugArray = [
                'de' => $slugDe,
                'en' => $slugEn,
            ];

            // Generate icon slug from title (e.g., "Umwelt und Ressourcenschutz" -> "umwelt_ressourcenschutz")
            $iconSlug = $this->generateIconSlug($slugDe);
            $iconPath = null;

            // Download icon if enabled
            if (config('seeding.media_download_enabled', true)) {
                $iconAssetPath = "handlungsfelder/{$iconSlug}.svg";
                $iconPath = $this->mediaDownloadService->downloadAsset($iconAssetPath, 'handlungsfelder');
                // Only log when a download actually produced a path
                if ($iconPath && $this->command) {
                    $this->command->info("Downloaded category icon: {$iconPath}");
                }
            }

            // Calculate source hash from parsed category data
            $sourceHash = $this->calculateSourceHash($parsedCategory);

            // For translatable fields, we need to search by the DE value
            // Using whereJsonContains or finding by checking all records
            $category = Category::whereJsonContains('slug->de', $slugDe)->first();

            $needsUpdate = false;

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group->id;
                $category->tenant_id = $group->tenant_id;
                $category->slug = $slugArray;
                // Only set icon if we have a valid path - use JSON format for consistency with SDGZielSeeder
                if (! empty($iconPath)) {
                    $category->icon = json_encode(['de' => $iconPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $category->position = $position;
                $category->last_synced_at = now();
                $category->source_hash = $sourceHash;
                $category->save();
            } else {
                // Update if needed
                if ($category->getTranslation('slug', 'de') !== $slugDe) {
                    $category->setTranslation('slug', 'de', $slugDe);
                    $needsUpdate = true;
                }
                // Update EN translation if different
                if ($slugEn && $category->getTranslation('slug', 'en') !== $slugEn) {
                    $category->setTranslation('slug', 'en', $slugEn);
                    $needsUpdate = true;
                }
                // Update icon only if we have a valid path and (existing icon is empty or different)
                if (! empty($iconPath)) {
                    $newIconJson = json_encode(['de' => $iconPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (empty($category->icon) || $category->icon !== $newIconJson) {
                        $category->icon = $newIconJson;
                        $needsUpdate = true;
                    }
                }
                // Update position if different
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
                // Update source hash and last_synced_at if data changed
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
     * Upserts and returns the "fields" CategoryGroup scoped to the resolved tenant.
     *
     * Resolves the tenant id and ensures a CategoryGroup with key "fields" exists for that tenant,
     * creating it with predefined attributes or updating the existing record.
     *
     * @return CategoryGroup|null The CategoryGroup instance after creation or update.
     */
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

    /**
     * Resolve the current tenant ID, preferring the Filament tenant when available and falling back to available tenants.
     *
     * Falls back to: 'default' slug -> 'stadt-regensburg' slug -> first available tenant.
     *
     * @return int|null The resolved tenant ID, or `null` if no tenant could be determined.
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

    /**
     * Generate a slug from the category title.
     */
    protected function generateSlug(string $title): string
    {
        return Str::slug($title);
    }

    /**
     * Generate icon slug from category title.
     * Converts "Umwelt und Ressourcenschutz" to "umwelt_ressourcenschutz"
     */
    protected function generateIconSlug(string $title): string
    {
        // Remove trailing spaces
        $title = trim($title);

        // Convert to lowercase
        $slug = mb_strtolower($title, 'UTF-8');

        // Remove "und" (and) as it's not in the icon filenames
        $slug = preg_replace('/\s+und\s+/u', '_', $slug);

        // Replace spaces and special characters with underscores
        $slug = preg_replace('/[^a-z0-9äöüß]+/u', '_', $slug);

        // Remove leading/trailing underscores
        $slug = trim($slug, '_');

        return $slug;
    }

    /**
     * Calculate SHA256 hash of the source data for change detection.
     */
    protected function calculateSourceHash(\App\Services\ParsedCategory $category): string
    {
        $dataToHash = [
            'id' => $category->id,
            'title' => $category->title,
        ];

        return hash('sha256', json_encode($dataToHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
