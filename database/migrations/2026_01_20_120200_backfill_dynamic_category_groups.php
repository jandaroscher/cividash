<?php

use App\Models\Category;
use App\Models\Tile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_groups')) {
            return;
        }

        // Use raw DB queries instead of Eloquent models to avoid dependency
        // on models that may be removed in the future
        $tenantIds = collect()
            ->merge(Category::query()->distinct()->pluck('tenant_id'));

        if (Schema::hasTable('handlungsdimensionen')) {
            $tenantIds = $tenantIds->merge(DB::table('handlungsdimensionen')->distinct()->pluck('tenant_id'));
        }
        if (Schema::hasTable('sdg_ziele')) {
            $tenantIds = $tenantIds->merge(DB::table('sdg_ziele')->distinct()->pluck('tenant_id'));
        }

        $tenantIds = $tenantIds->unique()->values();

        if ($tenantIds->isEmpty()) {
            $tenantIds = collect([null]);
        }

        foreach ($tenantIds as $tenantId) {
            $groups = $this->ensureDefaultGroups($tenantId);

            Category::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('category_group_id')
                ->update(['category_group_id' => $groups['fields']->id]);

            $dimensionCategoryMap = $this->backfillDimensionCategories($tenantId, $groups['dimensions']);
            $sdgCategoryMap = $this->backfillSdgCategories($tenantId, $groups['sdg']);

            $this->backfillTileDimensionLinks($tenantId, $dimensionCategoryMap);
            $this->backfillTileSdgLinks($tenantId, $sdgCategoryMap);
        }
    }

    public function down(): void
    {
        // No automatic rollback for data backfill
    }

    protected function ensureDefaultGroups(?int $tenantId): array
    {
        $now = now();
        $hasIsActive = Schema::hasColumn('category_groups', 'is_active');

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

        if (! $fieldsId) {
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

        if (! $dimensionsId) {
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

        if (! $sdgId) {
            $sdgId = DB::table('category_groups')->insertGetId($sdgData);
        } else {
            unset($sdgData['created_at']);
            DB::table('category_groups')->where('id', $sdgId)->update($sdgData);
        }

        return [
            'fields' => (object) ['id' => $fieldsId],
            'dimensions' => (object) ['id' => $dimensionsId],
            'sdg' => (object) ['id' => $sdgId],
        ];
    }

    protected function backfillDimensionCategories(?int $tenantId, object $group): array
    {
        $map = [];

        if (! Schema::hasTable('handlungsdimensionen')) {
            return $map;
        }

        $dimensions = DB::table('handlungsdimensionen')
            ->where('tenant_id', $tenantId)
            ->orderBy('position')
            ->get();

        foreach ($dimensions as $dimension) {
            $title = json_decode($dimension->title, true);
            $titleDe = $title['de'] ?? $title['en'] ?? 'Dimension';
            $translations = is_array($title) ? $title : ['de' => $titleDe];

            $category = Category::query()
                ->where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $titleDe)
                ->first();

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group->id;
                $category->tenant_id = $tenantId;
            }

            $category->slug = $translations;
            $category->icon = $dimension->icon;
            $category->color = $dimension->color ?? null;
            $category->position = $dimension->position ?? 0;
            $category->key = $dimension->key ?? null;
            $category->save();

            $map[$dimension->id] = $category->id;
        }

        return $map;
    }

    protected function backfillSdgCategories(?int $tenantId, object $group): array
    {
        $map = [];

        if (! Schema::hasTable('sdg_ziele')) {
            return $map;
        }

        $sdgZiele = DB::table('sdg_ziele')
            ->where('tenant_id', $tenantId)
            ->orderBy('position')
            ->get();

        foreach ($sdgZiele as $sdg) {
            $title = json_decode($sdg->title, true);
            $titleDe = $title['de'] ?? $title['en'] ?? 'SDG';
            $translations = is_array($title) ? $title : ['de' => $titleDe];

            $category = Category::query()
                ->where('category_group_id', $group->id)
                ->whereJsonContains('slug->de', $titleDe)
                ->first();

            if (! $category) {
                $category = new Category;
                $category->category_group_id = $group->id;
                $category->tenant_id = $tenantId;
            }

            $iconValue = $sdg->icon;
            if (is_string($iconValue)) {
                $decoded = json_decode($iconValue, true);
                if (is_array($decoded)) {
                    $iconValue = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $category->slug = $translations;
            $category->icon = $iconValue;
            $category->position = $sdg->position ?? 0;
            $category->save();

            $map[$sdg->id] = $category->id;
        }

        return $map;
    }

    protected function backfillTileDimensionLinks(?int $tenantId, array $dimensionCategoryMap): void
    {
        if ($dimensionCategoryMap === []) {
            return;
        }

        if (! Schema::hasColumn('tiles', 'handlungsdimension_id')) {
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

    protected function backfillTileSdgLinks(?int $tenantId, array $sdgCategoryMap): void
    {
        if ($sdgCategoryMap === []) {
            return;
        }

        if (! Schema::hasTable('tile_sdg_ziel')) {
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
