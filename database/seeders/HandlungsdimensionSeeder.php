<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Handlungsdimension;
use App\Services\MediaDownloadService;
use Illuminate\Database\Seeder;
use Illuminate\Console\Command;

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
     * Run the database seeds.
     *
     * @param array<string, array<int>> $handlungsfeldIdMap Map of original category ID to database ID
     * @return array<string, int> Map of dimension key to database ID
     */
    public function run(array $handlungsfeldIdMap = []): array
    {
        $dimensionIdMap = [];

        // Statische Definition der 3 Dimensionen
        $dimensions = [
            'grün' => [
                'title' => [
                    'de' => 'Grün',
                    'en' => 'Green',
                ],
                'icon_path' => 'dimensionen/gruen.svg',
                'position' => 0,
                'handlungsfeld_ids' => [542754, 542755, 542756], // Original IDs
            ],
            'gerecht' => [
                'title' => [
                    'de' => 'Gerecht',
                    'en' => 'Just',
                ],
                'icon_path' => 'dimensionen/gerecht.svg',
                'position' => 1,
                'handlungsfeld_ids' => [542748, 542752, 542753], // Original IDs
            ],
            'produktiv' => [
                'title' => [
                    'de' => 'Produktiv',
                    'en' => 'Productive',
                ],
                'icon_path' => 'dimensionen/produktiv.svg',
                'position' => 2,
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
                $dimension = new Handlungsdimension();
                $dimension->key = $key;
                $dimension->title = $data['title'];
                $dimension->icon = $iconPath; // Store as string (local path)
                $dimension->position = $data['position'];
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
                if ($needsUpdate) {
                    $dimension->save();
                }
            }

            $dimensionIdMap[$key] = $dimension->id;

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

        return $dimensionIdMap;
    }
}
