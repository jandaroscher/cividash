<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Handlungsdimension;
use App\Models\Tenant;
use App\Services\MediaDownloadService;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

/**
 * Seeder for Handlungsdimensionen (Action Dimensions).
 *
 * Seeds the 3 static dimensions: grün, gerecht, produktiv
 * with their mapping to Handlungsfelder (Categories).
 */
class HandlungsdimensionSeeder extends Seeder
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
     * Seed three predefined Handlungsdimensionen with their categories and optional handlungsfeld mappings.
     *
     * Creates or updates the static dimensions (grün, gerecht, produktiv), ensures a corresponding Category in the resolved "dimensions" CategoryGroup, downloads and assigns icons when enabled, and optionally maps existing handlungsfeld IDs to the created dimensions.
     *
     * @param  array<int,int>  $handlungsfeldIdMap  Map from original handlungsfeld ID to database ID used to associate handlungsfelder with dimensions; if empty no mapping is performed.
     * @return array<string, array<string,int>> Associative array with two keys:
     *                                          - `dimension_ids`: map of dimension key to the created/updated Handlungsdimension database ID.
     *                                          - `category_ids`: map of dimension key to the created/updated Category database ID.
     */
    public function run(array $handlungsfeldIdMap = []): array
    {
        $dimensionIdMap = [];
        $categoryIdMap = [];
        $group = $this->resolveDimensionsGroup();

        // Statische Definition der 3 Dimensionen
        $dimensions = [
            'grün' => [
                'title' => [
                    'de' => 'Grün',
                    'en' => 'Green',
                ],
                'icon_path' => 'dimensionen/gruen.svg',
                'position' => 0,
                'color' => '#dcfce7', // Entspricht bg-green-100 (Tailwind v4)
                'handlungsfeld_ids' => [542754, 542755, 542756], // Original IDs
            ],
            'gerecht' => [
                'title' => [
                    'de' => 'Gerecht',
                    'en' => 'Just',
                ],
                'icon_path' => 'dimensionen/gerecht.svg',
                'position' => 1,
                'color' => '#ffedd4', // Entspricht bg-orange-100 (Tailwind v4)
                'handlungsfeld_ids' => [542748, 542752, 542753], // Original IDs
            ],
            'produktiv' => [
                'title' => [
                    'de' => 'Produktiv',
                    'en' => 'Productive',
                ],
                'icon_path' => 'dimensionen/produktiv.svg',
                'position' => 2,
                'color' => '#dbeafe', // Entspricht bg-blue-100 (Tailwind v4)
                'handlungsfeld_ids' => [542749, 542750, 542751], // Original IDs
            ],
        ];

        foreach ($dimensions as $key => $data) {
            // Download icon and save as local path
            $iconPath = null;
            if (config('seeding.media_download_enabled', true)) {
                $iconPath = $this->mediaDownloadService->downloadAsset($data['icon_path'], 'dimensions');
                if ($this->command && $iconPath) {
                    $this->command->info("Downloaded Handlungsdimension icon: {$iconPath}");
                }
            }

            $dimension = Handlungsdimension::where('key', $key)->first();

            if (! $dimension) {
                $dimension = new Handlungsdimension;
                $dimension->key = $key;
                $dimension->title = $data['title'];
                $dimension->icon = $iconPath; // Store as string (local path)
                $dimension->position = $data['position'];
                $dimension->color = $data['color'] ?? null;
                $dimension->save();
            } else {
                // Update if needed
                $needsUpdate = false;
                if ($dimension->getTranslation('title', 'de') !== $data['title']['de']) {
                    $dimension->setTranslation('title', 'de', $data['title']['de']);
                    $needsUpdate = true;
                }
                if ($dimension->getTranslation('title', 'en') !== $data['title']['en']) {
                    $dimension->setTranslation('title', 'en', $data['title']['en']);
                    $needsUpdate = true;
                }
                if ($dimension->icon !== $iconPath) {
                    $dimension->icon = $iconPath;
                    $needsUpdate = true;
                }
                if ($dimension->position !== $data['position']) {
                    $dimension->position = $data['position'];
                    $needsUpdate = true;
                }
                if (isset($data['color']) && $dimension->color !== $data['color']) {
                    $dimension->color = $data['color'];
                    $needsUpdate = true;
                }
                if ($needsUpdate) {
                    $dimension->save();
                }
            }

            $category = Category::where('category_group_id', $group?->id)
                ->whereJsonContains('slug->de', $data['title']['de'])
                ->first();

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group?->id;
                $category->tenant_id = $dimension->tenant_id;
            }

            $category->slug = $data['title'];
            $category->icon = $iconPath;
            $category->color = $data['color'] ?? null;
            $category->position = $data['position'];
            $category->key = $key;
            $category->save();

            $dimensionIdMap[$key] = $dimension->id;
            $categoryIdMap[$key] = $category->id;

            // Map Handlungsfelder (Categories) to this dimension
            if (! empty($handlungsfeldIdMap)) {
                $mappedHandlungsfeldIds = [];
                foreach ($data['handlungsfeld_ids'] as $originalId) {
                    if (isset($handlungsfeldIdMap[$originalId])) {
                        $mappedHandlungsfeldIds[] = $handlungsfeldIdMap[$originalId];
                    }
                }
                $dimension->handlungsfelder()->sync($mappedHandlungsfeldIds);
            }

            if ($this->command) {
                $this->command->info("Handlungsdimension seeded: {$key} (ID: {$dimension->id})");
            }
        }

        return [
            'dimension_ids' => $dimensionIdMap,
            'category_ids' => $categoryIdMap,
        ];
    }

    /**
     * Resolve or create the "dimensions" CategoryGroup for the current tenant.
     *
     * Creates or updates a CategoryGroup with key `dimensions` (titles, position, filterable/color settings, and selection type)
     * scoped to the tenant returned by resolveTenantId().
     *
     * @return CategoryGroup|null The resolved or newly created CategoryGroup for dimensions, or `null` if a tenant cannot be determined.
     */
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

    /**
     * Resolve the current tenant's ID, preferring the Filament tenant when available and falling back to the tenant with slug "default".
     *
     * @return int|null The tenant ID if found, or `null` if no tenant could be resolved.
     */
    protected function resolveTenantId(): ?int
    {
        if (class_exists(\Filament\Facades\Filament::class) && \Filament\Facades\Filament::getTenant()) {
            return \Filament\Facades\Filament::getTenant()->id;
        }

        return Tenant::where('slug', 'default')->value('id');
    }
}
