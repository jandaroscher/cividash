<?php

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Handlungsdimension;
use App\Models\SDGZiel;
use App\Models\Tile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfills default category groups, creates category records for dimensions and SDGs, and links tiles to those categories per tenant.
     *
     * For each tenant (including null), ensures default CategoryGroup records exist, assigns uncategorized Categories to the "fields" group, creates or updates child Categories for Handlungsdimensionen and SDG-Ziele, and inserts/updates category_tile pivot rows to link tiles to the newly created categories.
     */
    public function up(): void
    {
        if (! Schema::hasTable('category_groups')) {
            return;
        }

        $tenantIds = collect()
            ->merge(Category::query()->distinct()->pluck('tenant_id'))
            ->merge(Handlungsdimension::query()->distinct()->pluck('tenant_id'))
            ->merge(SDGZiel::query()->distinct()->pluck('tenant_id'))
            ->unique()
            ->values();

        if ($tenantIds->isEmpty()) {
            $tenantIds = collect([null]);
        }

        foreach ($tenantIds as $tenantId) {
            $groups = $this->ensureDefaultGroups($tenantId);

            // Assign existing categories (handlungsfelder) to fields group
            Category::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('category_group_id')
                ->update(['category_group_id' => $groups['fields']->id]);

            // Create categories for handlungsdimensionen
            $dimensionCategoryMap = $this->backfillDimensionCategories($tenantId, $groups['dimensions']);

            // Create categories for SDG-Ziele
            $sdgCategoryMap = $this->backfillSdgCategories($tenantId, $groups['sdg']);

            // Link tiles to dimension categories via category_tile
            $this->backfillTileDimensionLinks($tenantId, $dimensionCategoryMap);

            // Link tiles to SDG categories via category_tile
            $this->backfillTileSdgLinks($tenantId, $sdgCategoryMap);
        }
    }

    /**
         * No-op rollback: this migration does not revert the data backfill.
         */
    public function down(): void
    {
        // No automatic rollback for data backfill
    }

    /**
     * Ensure the default category groups ("fields", "dimensions", "sdg") exist for the given tenant.
     * Uses raw DB queries to avoid BelongsToTenant trait issues during migrations.
     *
     * @param int|null $tenantId Tenant id to scope groups to, or null for global groups.
     * @return array<string, object> Associative array mapping group keys ('fields', 'dimensions', 'sdg') to objects with id property.
     */
    protected function ensureDefaultGroups(?int $tenantId): array
    {
        $now = now();
        $hasIsActive = Schema::hasColumn('category_groups', 'is_active');
        
        // Use raw DB queries to avoid BelongsToTenant trait issues
        $fieldsData = [
            'tenant_id' => $tenantId,
            'key' => 'fields',
            'title' => json_encode(['de' => 'Handlungsfelder', 'en' => 'Action Fields']),
            'position' => 0,
            'is_filterable' => true,
            'is_color_source' => false,
            'selection_type' => 'multi',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($hasIsActive) {
            $fieldsData['is_active'] = true;
        }
        
        $fieldsId = DB::table('category_groups')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->where('key', 'fields')
            ->value('id');
            
        if (!$fieldsId) {
            $fieldsId = DB::table('category_groups')->insertGetId($fieldsData);
        } else {
            unset($fieldsData['created_at']);
            DB::table('category_groups')->where('id', $fieldsId)->update($fieldsData);
        }

        $dimensionsData = [
            'tenant_id' => $tenantId,
            'key' => 'dimensions',
            'title' => json_encode(['de' => 'Handlungsdimensionen', 'en' => 'Action Dimensions']),
            'position' => 1,
            'is_filterable' => true,
            'is_color_source' => true,
            'selection_type' => 'single',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($hasIsActive) {
            $dimensionsData['is_active'] = true;
        }
        
        $dimensionsId = DB::table('category_groups')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->where('key', 'dimensions')
            ->value('id');
            
        if (!$dimensionsId) {
            $dimensionsId = DB::table('category_groups')->insertGetId($dimensionsData);
        } else {
            unset($dimensionsData['created_at']);
            DB::table('category_groups')->where('id', $dimensionsId)->update($dimensionsData);
        }

        $sdgData = [
            'tenant_id' => $tenantId,
            'key' => 'sdg',
            'title' => json_encode(['de' => 'SDG-Ziele', 'en' => 'SDG Goals']),
            'position' => 2,
            'is_filterable' => true,
            'is_color_source' => false,
            'selection_type' => 'multi',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($hasIsActive) {
            $sdgData['is_active'] = true;
        }
        
        $sdgId = DB::table('category_groups')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->where('key', 'sdg')
            ->value('id');
            
        if (!$sdgId) {
            $sdgId = DB::table('category_groups')->insertGetId($sdgData);
        } else {
            unset($sdgData['created_at']);
            DB::table('category_groups')->where('id', $sdgId)->update($sdgData);
        }

        // Return simple objects with id property instead of Eloquent models
        // to avoid BelongsToTenant global scope issues
        return [
            'fields' => (object) ['id' => $fieldsId],
            'dimensions' => (object) ['id' => $dimensionsId],
            'sdg' => (object) ['id' => $sdgId],
        ];
    }

    /**
     * Ensure a Category exists under the given group for each Handlungsdimension of the tenant,
     * creating or updating categories and applying title translations, icon, color, position, and key.
     *
     * @param int|null $tenantId The tenant id to operate on, or null for global records.
     * @param object $group The parent category group object with id property.
     * @return array<int,int> Map of Handlungsdimension id => created or updated Category id.
     */
    protected function backfillDimensionCategories(?int $tenantId, object $group): array
    {
        $map = [];
        $dimensions = Handlungsdimension::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('position')
            ->get();

        foreach ($dimensions as $dimension) {
            $titleDe = $dimension->getTranslation('title', 'de', false)
                ?: $dimension->getTranslation('title', 'en', false)
                ?: 'Dimension';
            $translations = $dimension->getTranslations('title');

            $category = Category::query()
                ->where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $titleDe)
                ->first();

            if (! $category) {
                $category = new Category();
                $category->category_group_id = $group->id;
                $category->tenant_id = $tenantId;
            }

            $category->slug = $translations ?: ['de' => $titleDe];
            $category->icon = $dimension->icon;
            $category->color = $dimension->color;
            $category->position = $dimension->position ?? 0;
            $category->key = $dimension->key;
            $category->save();

            $map[$dimension->id] = $category->id;
        }

        return $map;
    }

    /**
     * Create or update category records for SDG‑Ziele under the given category group.
     *
     * @param int|null $tenantId Tenant id used to scope which SDG‑Ziele are processed, or null for global entries.
     * @param object $group The category group object with id property.
     * @return array<int,int> Map where each key is an SDGZiel id and each value is the corresponding Category id.
     */
    protected function backfillSdgCategories(?int $tenantId, object $group): array
    {
        $map = [];
        $sdgZiele = SDGZiel::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('position')
            ->get();

        foreach ($sdgZiele as $sdg) {
            $titleDe = $sdg->getTranslation('title', 'de', false)
                ?: $sdg->getTranslation('title', 'en', false)
                ?: 'SDG';
            $translations = $sdg->getTranslations('title');

            $category = Category::query()
                ->where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $titleDe)
                ->first();

            if (! $category) {
                $category = new Category();
                $category->category_group_id = $group->id;
                $category->tenant_id = $tenantId;
            }

            $iconValue = $sdg->icon;
            if (is_array($iconValue)) {
                $iconValue = json_encode($iconValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $category->slug = $translations ?: ['de' => $titleDe];
            $category->icon = $iconValue;
            $category->position = $sdg->position ?? 0;
            $category->save();

            $map[$sdg->id] = $category->id;
        }

        return $map;
    }

    /**
     * Link tiles that reference Handlungsdimensionen to their corresponding categories in the category_tile pivot.
     *
     * Processes tiles scoped to the given tenant (or global when null) that have a non-null handlungsdimension_id and inserts or updates category_tile rows using the provided mapping.
     *
     * @param int|null $tenantId The tenant ID to scope the backfill, or null to operate on global (no-tenant) records.
     * @param array<int,int> $dimensionCategoryMap Map of handlungsdimension_id => category_id used to create or update pivot entries.
     */
    protected function backfillTileDimensionLinks(?int $tenantId, array $dimensionCategoryMap): void
    {
        if ($dimensionCategoryMap === []) {
            return;
        }

        $tiles = Tile::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('handlungsdimension_id')
            ->get(['id', 'handlungsdimension_id']);

        foreach ($tiles as $tile) {
            $categoryId = $dimensionCategoryMap[$tile->handlungsdimension_id] ?? null;
            if (! $categoryId) {
                continue;
            }

            DB::table('category_tile')->updateOrInsert([
                'tile_id' => $tile->id,
                'category_id' => $categoryId,
            ], []);
        }
    }

    /**
     * Link tiles' SDG relations to their corresponding categories in the category_tile pivot for the given tenant.
     *
     * Inserts or updates pivot rows so each tile referenced in tile_sdg_ziel is associated with the mapped category.
     *
     * @param array<int,int> $sdgCategoryMap Map of SDG Ziel IDs to category IDs used when creating/updating pivot rows.
     */
    protected function backfillTileSdgLinks(?int $tenantId, array $sdgCategoryMap): void
    {
        if ($sdgCategoryMap === []) {
            return;
        }

        $sdgLinks = DB::table('tile_sdg_ziel')
            ->join('tiles', 'tile_sdg_ziel.tile_id', '=', 'tiles.id')
            ->where('tiles.tenant_id', $tenantId)
            ->select('tile_sdg_ziel.tile_id', 'tile_sdg_ziel.sdg_ziel_id')
            ->get();

        foreach ($sdgLinks as $link) {
            $categoryId = $sdgCategoryMap[$link->sdg_ziel_id] ?? null;
            if (! $categoryId) {
                continue;
            }

            DB::table('category_tile')->updateOrInsert([
                'tile_id' => $link->tile_id,
                'category_id' => $categoryId,
            ], []);
        }
    }
};