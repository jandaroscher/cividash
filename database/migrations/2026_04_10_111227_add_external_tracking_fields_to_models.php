<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add external_source and external_id to models that can be synchronised
     * from an external data platform such as CIVITAS/CORE.
     *
     * Together with the existing last_synced_at and source_hash columns on
     * tiles and categories, these fields enable incremental sync and
     * provenance tracking for externally managed records.
     */
    public function up(): void
    {
        $tables = ['tiles', 'categories', 'category_groups', 'metric_definitions'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'external_source')) {
                    $blueprint->string('external_source')->nullable()->after('tenant_id');
                }
                if (! Schema::hasColumn($table, 'external_id')) {
                    $blueprint->string('external_id')->nullable()->after('external_source');
                }

                // Add last_synced_at and source_hash if not already present
                // (tiles and categories already have them from earlier migrations)
                if (! Schema::hasColumn($table, 'last_synced_at')) {
                    $blueprint->timestamp('last_synced_at')->nullable();
                }
                if (! Schema::hasColumn($table, 'source_hash')) {
                    $blueprint->string('source_hash')->nullable();
                }
            });

            // Composite index for efficient lookup during sync
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $indexName = $table.'_external_source_external_id_index';
                if (! Schema::hasIndex($table, $indexName)) {
                    $blueprint->index(['external_source', 'external_id'], $indexName);
                }
            });
        }
    }

    public function down(): void
    {
        // Mirror up() exactly. external_source/external_id (+ the composite
        // index) are added to every table here, so they are always dropped.
        // last_synced_at/source_hash already exist on tiles and categories
        // from earlier migrations (2025_11_28_120000/120001) — up() only adds
        // them to category_groups and metric_definitions, so down() must only
        // remove them from those same two tables to avoid dropping columns this
        // migration never created.
        $allTables = ['tiles', 'categories', 'category_groups', 'metric_definitions'];
        $syncColumnTables = ['category_groups', 'metric_definitions'];

        foreach ($allTables as $table) {
            // Drop the composite index before its columns.
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $indexName = $table.'_external_source_external_id_index';
                if (Schema::hasIndex($table, $indexName)) {
                    $blueprint->dropIndex($indexName);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table, $syncColumnTables) {
                $columns = [];

                if (Schema::hasColumn($table, 'external_id')) {
                    $columns[] = 'external_id';
                }
                if (Schema::hasColumn($table, 'external_source')) {
                    $columns[] = 'external_source';
                }

                if (in_array($table, $syncColumnTables, true)) {
                    if (Schema::hasColumn($table, 'source_hash')) {
                        $columns[] = 'source_hash';
                    }
                    if (Schema::hasColumn($table, 'last_synced_at')) {
                        $columns[] = 'last_synced_at';
                    }
                }

                if (! empty($columns)) {
                    $blueprint->dropColumn($columns);
                }
            });
        }
    }
};
