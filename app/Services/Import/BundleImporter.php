<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\Import\Support\ImportDiff;
use App\Services\Import\Support\ImportError;
use App\Services\Import\Support\ImportResult;
use App\Services\Import\Support\ImportWarning;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Runs a parsed upload bundle against the DB. Supports two modes:
 *
 *  - `dry_run` — performs all upserts inside a transaction, then rolls back
 *    and returns the diff. Guarantees that a successful dry-run is also a
 *    successful commit.
 *  - `commit`  — performs all upserts inside a transaction and commits.
 *
 * A single per-row domain-level error aborts the entire run (atomic import).
 * The list of errors is collected first (validation pass), only if empty
 * do we actually touch the DB (execution pass).
 */
class BundleImporter
{
    /** @var list<ImportError> */
    private array $errors = [];

    /** @var list<ImportWarning> */
    private array $warnings = [];

    private ImportDiff $diff;

    /** @var array<string, CategoryGroup> group_key => group */
    private array $groupCache = [];

    /** @var array<string, Category> "group_key/cat_key" => category */
    private array $categoryCache = [];

    /** @var array<string, Tile> slug_de => tile */
    private array $tileCache = [];

    private AdminRuleExtractor $rules;

    public function __construct(private readonly Tenant $tenant)
    {
        $this->rules = new AdminRuleExtractor($tenant);
    }

    /**
     * Run the Admin API FormRequest rules for a given entity against the
     * supplied data. Produces structured errors with the bundle path prefix
     * (e.g. `data[3].tile.title.de`) so the caller can surface them in the
     * same shape as other import errors.
     *
     * Existence-based rules (`Rule::exists('tiles', 'id')->where('tenant_id', ...)`)
     * aren't meaningful at import time — we resolve FK references via natural
     * keys in the importer. We therefore drop those rules before validating.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    private function validateAgainstAdminRules(array $data, array $rules, string $pathPrefix, ?int $row = null): bool
    {
        // Existence rules depend on parent FK columns we haven't filled in
        // yet (tile_id, metric_definition_id, time_period_id, category_group_id);
        // the importer resolves those separately via natural keys.
        $rules = collect($rules)
            ->map(fn ($ruleList) => is_array($ruleList)
                ? array_values(array_filter(
                    $ruleList,
                    fn ($r) => ! ($r instanceof \Illuminate\Validation\Rules\Exists)
                ))
                : $ruleList)
            ->reject(fn ($ruleList, string $field) => in_array($field, [
                'tile_id', 'metric_definition_id', 'time_period_id', 'category_group_id', 'handlungsdimension_id',
            ], true))
            ->all();

        $validator = Validator::make($data, $rules);
        if ($validator->passes()) {
            return true;
        }

        foreach ($validator->errors()->messages() as $field => $messages) {
            $this->errors[] = new ImportError(
                path: trim($pathPrefix.'.'.$field, '.'),
                code: 'validation_failed',
                message: $messages[0] ?? 'Validation failed.',
                row: $row,
            );
        }

        return false;
    }

    public function run(array $bundle, string $mode): ImportResult
    {
        $this->errors = [];
        $this->warnings = [];
        $this->diff = new ImportDiff;

        $started = microtime(true);

        $this->validateTenantSlug($bundle);

        $bundleMode = $bundle['mode'] ?? 'upsert';
        if ($bundleMode === 'replace') {
            // `replace` (destructive sync — delete records missing from bundle)
            // is documented in the schema but not yet implemented. Reject it
            // explicitly rather than silently behave like `upsert`, which would
            // mislead callers relying on the destructive-sync semantics.
            $this->errors[] = new ImportError(
                path: 'mode',
                code: 'unsupported_mode',
                message: 'mode "replace" is not yet implemented. Use "upsert" in v1.0; destructive sync is planned for v1.1.',
            );

            return new ImportResult(
                mode: $mode,
                status: 'failed',
                diff: new ImportDiff,
                errors: $this->errors,
                warnings: $this->warnings,
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
        }

        DB::beginTransaction();
        try {
            $this->upsertCategoryGroups($bundle['category_groups'] ?? []);
            $this->upsertCategories($bundle['categories'] ?? []);
            $this->processRows($bundle['data'] ?? []);

            if (! empty($this->errors)) {
                DB::rollBack();

                return new ImportResult(
                    mode: $mode,
                    status: 'failed',
                    diff: new ImportDiff, // diff is meaningless if we rolled back on errors
                    errors: $this->errors,
                    warnings: $this->warnings,
                    durationMs: (int) ((microtime(true) - $started) * 1000),
                );
            }

            if ($mode === 'dry_run') {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            // Never surface raw exception messages to the client — they can
            // leak SQL, filesystem, or framework internals. Log server-side
            // with enough correlation data, return a generic error.
            Log::error('Import execution error', [
                'tenant_id' => $this->tenant->id,
                'mode' => $mode,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);

            $this->errors[] = new ImportError(
                path: 'bundle',
                code: 'execution_error',
                message: 'An internal execution error occurred during the import. Check the server logs for details.',
            );

            return new ImportResult(
                mode: $mode,
                status: 'failed',
                diff: new ImportDiff,
                errors: $this->errors,
                warnings: $this->warnings,
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
        }

        return new ImportResult(
            mode: $mode,
            status: 'success',
            diff: $this->diff,
            errors: [],
            warnings: $this->warnings,
            durationMs: (int) ((microtime(true) - $started) * 1000),
        );
    }

    private function validateTenantSlug(array $bundle): void
    {
        $bundleSlug = $bundle['tenant']['slug'] ?? null;
        if ($bundleSlug !== null && $bundleSlug !== $this->tenant->slug) {
            $this->warnings[] = new ImportWarning(
                path: 'tenant.slug',
                code: 'tenant_mismatch',
                message: "Bundle tenant '{$bundleSlug}' differs from token tenant '{$this->tenant->slug}'. Data will be imported into '{$this->tenant->slug}'.",
            );
        }
    }

    private function upsertCategoryGroups(array $groups): void
    {
        foreach ($groups as $i => $g) {
            $key = $g['key'] ?? null;
            if ($key === null || $key === '') {
                $this->errors[] = new ImportError(
                    path: "category_groups[$i].key",
                    code: 'required',
                    message: 'category_groups[].key is required.',
                );

                continue;
            }

            $existing = CategoryGroup::where('tenant_id', $this->tenant->id)->where('key', $key)->first();

            $attributes = array_filter([
                'title' => $g['title'] ?? null,
                'position' => $g['position'] ?? null,
                'is_active' => $g['is_active'] ?? null,
            ], fn ($v) => $v !== null);

            if ($existing === null && ! $this->validateAgainstAdminRules(
                array_merge(['key' => $key], $attributes),
                $this->rules->categoryGroupRules(),
                "category_groups[$i]",
            )) {
                continue;
            }

            if ($existing === null) {
                $existing = CategoryGroup::make(array_merge(['key' => $key], $attributes));
                $existing->tenant()->associate($this->tenant);
                $existing->save();
                $this->diff->record('category_groups', 'create');
            } elseif (! empty($attributes)) {
                $attributes = $this->mergeTranslatableAttributes($existing, $attributes);
                if ($this->hasChanges($existing, $attributes)) {
                    $existing->fill($attributes);
                    $existing->save();
                    $this->diff->record('category_groups', 'update');
                } else {
                    $this->diff->record('category_groups', 'unchanged');
                }
            } else {
                $this->diff->record('category_groups', 'unchanged');
            }

            $this->groupCache[$key] = $existing;
        }
    }

    private function upsertCategories(array $categories): void
    {
        foreach ($categories as $i => $c) {
            $groupKey = $c['group_key'] ?? null;
            $catKey = $c['key'] ?? null;

            if ($groupKey === null || $catKey === null) {
                $this->errors[] = new ImportError(
                    path: "categories[$i]",
                    code: 'required',
                    message: 'categories[].group_key and categories[].key are required.',
                );

                continue;
            }

            $group = $this->resolveGroup($groupKey);
            if ($group === null) {
                $this->errors[] = new ImportError(
                    path: "categories[$i].group_key",
                    code: 'unresolved_reference',
                    message: "Category group '{$groupKey}' does not exist.",
                );

                continue;
            }

            $existing = Category::where('tenant_id', $this->tenant->id)
                ->where('category_group_id', $group->id)
                ->where('key', $catKey)
                ->first();

            $attributes = array_filter([
                'slug' => $c['slug'] ?? null,
                'icon' => $c['icon'] ?? null,
                'color' => $c['color'] ?? null,
                'is_active' => $c['is_active'] ?? null,
                'position' => $c['position'] ?? null,
            ], fn ($v) => $v !== null);

            if ($existing === null && ! $this->validateAgainstAdminRules(
                array_merge(['key' => $catKey, 'category_group_id' => $group->id], $attributes),
                $this->rules->categoryRules(),
                "categories[$i]",
            )) {
                continue;
            }

            if ($existing === null) {
                $existing = Category::make(array_merge([
                    'key' => $catKey,
                    'category_group_id' => $group->id,
                ], $attributes));
                $existing->tenant()->associate($this->tenant);
                $existing->save();
                $this->diff->record('categories', 'create');
            } elseif (! empty($attributes)) {
                $attributes = $this->mergeTranslatableAttributes($existing, $attributes);
                if ($this->hasChanges($existing, $attributes)) {
                    $existing->fill($attributes);
                    $existing->save();
                    $this->diff->record('categories', 'update');
                } else {
                    $this->diff->record('categories', 'unchanged');
                }
            } else {
                $this->diff->record('categories', 'unchanged');
            }

            $this->categoryCache[$groupKey.'/'.$catKey] = $existing;
        }
    }

    private function processRows(array $rows): void
    {
        // Group rows by tile.slug so we can upsert tile + its metrics + values together.
        // NOTE: `mode` intentionally not accepted here — `replace` is rejected early in run();
        // a future v1.1 implementation would add a separate deletePass() after this method.
        $grouped = [];
        foreach ($rows as $idx => $row) {
            $slug = $row['tile.slug'] ?? null;
            if ($slug === null || $slug === '') {
                $this->errors[] = new ImportError(
                    path: "data[$idx].tile.slug",
                    code: 'required',
                    message: 'tile.slug is required.',
                    row: $idx,
                );

                continue;
            }

            $grouped[$slug][] = ['row' => $row, 'idx' => $idx];
        }

        foreach ($grouped as $slug => $entries) {
            $this->processTileGroup((string) $slug, $entries);
        }
    }

    /**
     * @param  list<array{row: array<string,mixed>, idx: int}>  $entries
     */
    private function processTileGroup(string $slug, array $entries): void
    {
        // Use the first row as the source of truth for tile-level attributes.
        $firstRow = $entries[0]['row'];
        $firstIdx = $entries[0]['idx'];

        $tile = $this->upsertTile($slug, $firstRow, $firstIdx);
        if ($tile === null) {
            return; // error already recorded
        }

        // Collect category keys across all entries of this tile (same tile → same categories).
        $catKeys = [];
        foreach ($entries as $entry) {
            foreach ((array) ($entry['row']['category.keys'] ?? []) as $ck) {
                $catKeys[$ck] = true;
            }
        }
        $this->syncTileCategories($tile, array_keys($catKeys), $firstIdx);

        // Upsert metric definitions (one per unique metric.key).
        $metricDefsByKey = [];
        foreach ($entries as $entry) {
            $metricKey = $entry['row']['metric.key'] ?? null;
            if ($metricKey === null || $metricKey === '') {
                continue;
            }
            if (isset($metricDefsByKey[$metricKey])) {
                continue;
            }
            $metricDefsByKey[$metricKey] = $this->upsertMetricDefinition($tile, (string) $metricKey, $entry['row'], $entry['idx']);
        }

        // Upsert time periods (one per unique value.year).
        //
        // TileYear was replaced by TimePeriod (granularity + period_key).
        // The bundle schema keeps `value.year` as the natural key (symmetric with
        // the JSON export, whose `value.year` is derived from period_key); the
        // importer maps it to a year-granularity TimePeriod whose period_key is the
        // 4-digit year string — the same pattern as MetricSeeder and
        // TileExportCollector::periodYear(). Granular (quarter/month/...) periods
        // are a planned v1.1 schema extension and are out of scope here.
        $timePeriodsByYear = [];
        foreach ($entries as $entry) {
            $year = $entry['row']['value.year'] ?? null;
            if ($year === null) {
                continue;
            }
            $year = (int) $year;
            if (isset($timePeriodsByYear[$year])) {
                continue;
            }
            $timePeriodsByYear[$year] = $this->upsertTimePeriod($tile, $year);
        }

        // Upsert metric values.
        foreach ($entries as $entry) {
            $row = $entry['row'];
            $idx = $entry['idx'];

            $metricKey = $row['metric.key'] ?? null;
            $year = $row['value.year'] ?? null;

            if ($metricKey === null || $year === null) {
                continue;
            }

            $md = $metricDefsByKey[$metricKey] ?? null;
            $tp = $timePeriodsByYear[(int) $year] ?? null;

            if ($md === null || $tp === null) {
                continue; // upsert above already recorded errors
            }

            $value = $row['value.value'] ?? null;
            if ($value === null) {
                continue; // year/metric exists but no value — OK, skip.
            }

            if (! is_numeric($value)) {
                $this->errors[] = new ImportError(
                    path: "data[$idx].value.value",
                    code: 'invalid_type',
                    message: 'value.value must be numeric.',
                    row: $idx,
                );

                continue;
            }

            $existing = MetricValue::where('tenant_id', $this->tenant->id)
                ->where('metric_definition_id', $md->id)
                ->where('time_period_id', $tp->id)
                ->first();

            $attributes = [
                'value' => (float) $value,
                'sort_order' => $row['value.sort_order'] ?? ($existing?->sort_order ?? 0),
            ];

            if ($existing === null && ! $this->validateAgainstAdminRules(
                array_merge(['metric_definition_id' => $md->id, 'time_period_id' => $tp->id], $attributes),
                $this->rules->metricValueRules(),
                "data[$idx].value",
                $idx,
            )) {
                continue;
            }

            if ($existing === null) {
                $mv = MetricValue::make(array_merge([
                    'metric_definition_id' => $md->id,
                    'time_period_id' => $tp->id,
                    'is_active' => true,
                ], $attributes));
                $mv->tenant()->associate($this->tenant);
                $mv->save();
                $this->diff->record('metric_values', 'create');
            } elseif ($this->hasChanges($existing, $attributes)) {
                $existing->fill($attributes);
                $existing->save();
                $this->diff->record('metric_values', 'update');
            } else {
                $this->diff->record('metric_values', 'unchanged');
            }
        }
    }

    private function upsertTile(string $slug, array $row, int $idx): ?Tile
    {
        $existing = Tile::where('tenant_id', $this->tenant->id)
            ->where('slug->de', $slug)
            ->first();

        $title = $row['tile.title'] ?? null;
        if ($existing === null) {
            if (! is_array($title) || empty($title['de'] ?? null)) {
                $this->errors[] = new ImportError(
                    path: "data[$idx].tile.title.de",
                    code: 'required',
                    message: 'tile.title.de is required when creating a new tile.',
                    row: $idx,
                );

                return null;
            }
        }

        // `slug` is seeded with the documented `tile.slug` (de). The en-slug
        // is auto-generated from `title.en` by Tile::buildSlugs() when the
        // model saves — see app/Models/Tile.php. We intentionally do not
        // read undocumented input keys like `tile.slug_en` here.
        $attributes = array_filter([
            'title' => $title,
            'description' => $row['tile.description'] ?? null,
            'hint' => $row['tile.hint'] ?? null,
            'position' => $row['tile.position'] ?? null,
            'slug' => ['de' => $slug],
        ], fn ($v) => $v !== null);

        if ($existing === null && ! $this->validateAgainstAdminRules(
            $attributes,
            $this->rules->tileRules(),
            "data[$idx].tile",
            $idx,
        )) {
            return null;
        }

        if ($existing === null) {
            $existing = Tile::make(array_merge(['is_public' => true], $attributes));
            $existing->tenant()->associate($this->tenant);
            $existing->save();
            $this->diff->record('tiles', 'create');
        } else {
            $attributes = $this->mergeTranslatableAttributes($existing, $attributes);
            if ($this->hasChanges($existing, $attributes)) {
                $existing->fill($attributes);
                $existing->save();
                $this->diff->record('tiles', 'update');
            } else {
                $this->diff->record('tiles', 'unchanged');
            }
        }

        $this->tileCache[$slug] = $existing;

        return $existing;
    }

    /**
     * @param  list<string>  $categoryKeys
     */
    private function syncTileCategories(Tile $tile, array $categoryKeys, int $idx): void
    {
        if (empty($categoryKeys)) {
            return;
        }

        $ids = [];
        foreach ($categoryKeys as $key) {
            $cat = $this->resolveCategory($key);
            if ($cat === null) {
                $this->errors[] = new ImportError(
                    path: "data[$idx].category.keys",
                    code: 'unresolved_reference',
                    message: "Category '{$key}' does not exist.",
                    row: $idx,
                );

                continue;
            }
            $ids[] = $cat->id;
        }

        if (! empty($ids)) {
            // upsert semantics: add the bundle's category links but never
            // silently remove categories a tile already has. Destructive
            // replace of category associations is reserved for v1.1
            // (mode=replace), consistent with the rest of the importer.
            $tile->categories()->syncWithoutDetaching($ids);
        }
    }

    private function upsertMetricDefinition(Tile $tile, string $metricKey, array $row, int $idx): ?MetricDefinition
    {
        $existing = MetricDefinition::where('tenant_id', $this->tenant->id)
            ->where('tile_id', $tile->id)
            ->where('metric_key', $metricKey)
            ->first();

        $label = $row['metric.label'] ?? null;
        if ($existing === null) {
            if (! is_array($label) || empty($label['de'] ?? null)) {
                $this->errors[] = new ImportError(
                    path: "data[$idx].metric.label.de",
                    code: 'required',
                    message: 'metric.label.de is required when creating a new metric definition.',
                    row: $idx,
                );

                return null;
            }
        }

        $attributes = array_filter([
            'label' => $label,
            'unit' => $row['metric.unit'] ?? null,
            'indicator_type' => $row['metric.indicator_type'] ?? null,
        ], fn ($v) => $v !== null);

        if ($existing === null && ! $this->validateAgainstAdminRules(
            array_merge(['tile_id' => $tile->id, 'metric_key' => $metricKey], $attributes),
            $this->rules->metricDefinitionRules(),
            "data[$idx].metric",
            $idx,
        )) {
            return null;
        }

        if ($existing === null) {
            $existing = MetricDefinition::make(array_merge([
                'tile_id' => $tile->id,
                'metric_key' => $metricKey,
                'is_active' => true,
            ], $attributes));
            $existing->tenant()->associate($this->tenant);
            $existing->save();
            $this->diff->record('metric_definitions', 'create');
        } else {
            $attributes = $this->mergeTranslatableAttributes($existing, $attributes);
            if ($this->hasChanges($existing, $attributes)) {
                $existing->fill($attributes);
                $existing->save();
                $this->diff->record('metric_definitions', 'update');
            } else {
                $this->diff->record('metric_definitions', 'unchanged');
            }
        }

        return $existing;
    }

    /**
     * Resolve or create the year-granularity TimePeriod for a tile.
     *
     * The bundle's natural key for a value is the calendar year (`value.year`);
     * it maps to a TimePeriod with granularity='year' and period_key set to the
     * 4-digit year string — the canonical mapping used by MetricSeeder and
     * TileExportCollector::periodYear(). Sub-year granularities are reserved for
     * the planned v1.1 schema extension.
     */
    private function upsertTimePeriod(Tile $tile, int $year): TimePeriod
    {
        $periodKey = (string) $year;

        $existing = TimePeriod::where('tenant_id', $this->tenant->id)
            ->where('tile_id', $tile->id)
            ->where('period_key', $periodKey)
            ->first();

        if ($existing === null) {
            $existing = TimePeriod::make([
                'tile_id' => $tile->id,
                'granularity' => 'year',
                'period_key' => $periodKey,
                'label' => $periodKey,
                'sort' => 0,
            ]);
            $existing->tenant()->associate($this->tenant);
            $existing->save();
            $this->diff->record('time_periods', 'create');
        } else {
            $this->diff->record('time_periods', 'unchanged');
        }

        return $existing;
    }

    private function resolveGroup(string $key): ?CategoryGroup
    {
        if (isset($this->groupCache[$key])) {
            return $this->groupCache[$key];
        }

        $group = CategoryGroup::where('tenant_id', $this->tenant->id)->where('key', $key)->first();
        if ($group) {
            $this->groupCache[$key] = $group;
        }

        return $group;
    }

    private function resolveCategory(string $qualifiedKey): ?Category
    {
        if (isset($this->categoryCache[$qualifiedKey])) {
            return $this->categoryCache[$qualifiedKey];
        }

        [$groupKey, $catKey] = array_pad(explode('/', $qualifiedKey, 2), 2, null);
        if ($groupKey === null || $catKey === null) {
            return null;
        }

        $group = $this->resolveGroup($groupKey);
        if ($group === null) {
            return null;
        }

        $cat = Category::where('tenant_id', $this->tenant->id)
            ->where('category_group_id', $group->id)
            ->where('key', $catKey)
            ->first();

        if ($cat) {
            $this->categoryCache[$qualifiedKey] = $cat;
        }

        return $cat;
    }

    /**
     * Merge translatable attributes so that sending only `{de: ...}` does not
     * wipe an existing `en` translation. Non-translatable attributes pass
     * through unchanged.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function mergeTranslatableAttributes(Model $model, array $attributes): array
    {
        $translatable = property_exists($model, 'translatable') ? (array) $model->translatable : [];
        if (empty($translatable)) {
            return $attributes;
        }

        foreach ($attributes as $key => $value) {
            if (! in_array($key, $translatable, true) || ! is_array($value)) {
                continue;
            }
            $current = $model->getAttribute($key);
            if (is_array($current)) {
                $attributes[$key] = array_merge($current, $value);
            }
        }

        return $attributes;
    }

    /**
     * Whether any attribute in $attributes differs from the current model state.
     * Arrays are compared loosely so that JSON-cast fields (translations) match
     * regardless of PHP key order; scalars are compared with strict `!==`
     * after type coercion Laravel would apply (e.g. boolean casts), so
     * null/false/"" are not treated as equal.
     */
    private function hasChanges(Model $model, array $attributes): bool
    {
        foreach ($attributes as $key => $new) {
            $current = $model->getAttribute($key);
            if (is_array($new) || is_array($current)) {
                if ($this->normalizeArray($current) !== $this->normalizeArray($new)) {
                    return true;
                }
            } elseif ($current !== $new) {
                return true;
            }
        }

        return false;
    }

    private function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        ksort($value);

        return $value;
    }
}
