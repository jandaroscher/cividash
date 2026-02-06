<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column already exists (in case migration was partially run)
        if (! Schema::hasColumn('metrics', 'metric_key')) {
            Schema::table('metrics', function (Blueprint $table) {
                // Add metric_key column (nullable initially for existing records)
                $table->string('metric_key')->nullable()->after('tile_year_id');
            });
        }

        // Generate metric_key for existing records based on label
        // This ensures existing data has a key before we make it required
        // Process in chunks to avoid memory issues with large tables
        // Handle duplicates by appending a counter to make keys unique per tile_year_id
        // First, load existing metric_key values to avoid conflicts
        $processedKeys = []; // Track [tile_year_id => [key => count]]

        // Pre-populate processedKeys with existing metric_key values
        // This ensures we don't create duplicates with already-assigned keys
        DB::table('metrics')
            ->whereNotNull('metric_key')
            ->select('tile_year_id', 'metric_key')
            ->orderBy('id')
            ->chunkById(500, function ($metrics) use (&$processedKeys) {
                foreach ($metrics as $metric) {
                    $tileYearId = $metric->tile_year_id;
                    $existingKey = $metric->metric_key;

                    if (! isset($processedKeys[$tileYearId])) {
                        $processedKeys[$tileYearId] = [];
                    }

                    // Extract base key (without suffix) and track it
                    // If key ends with -N, extract base; otherwise use as-is
                    if (preg_match('/^(.+)-(\d+)$/', $existingKey, $matches)) {
                        $baseKey = $matches[1];
                        $suffix = (int) $matches[2];
                        // Track the highest suffix used for this base key
                        if (! isset($processedKeys[$tileYearId][$baseKey]) || $processedKeys[$tileYearId][$baseKey] < $suffix) {
                            $processedKeys[$tileYearId][$baseKey] = $suffix;
                        }
                    } else {
                        // Key without suffix - this is the "base" key (equivalent to -0)
                        // Set counter to 0 so next duplicate becomes -1
                        if (! isset($processedKeys[$tileYearId][$existingKey])) {
                            $processedKeys[$tileYearId][$existingKey] = 0;
                        }
                    }
                }
            });

        // Now process metrics that don't have a metric_key yet
        DB::table('metrics')
            ->whereNull('metric_key')
            ->orderBy('id')
            ->chunkById(500, function ($metrics) use (&$processedKeys) {
                foreach ($metrics as $metric) {
                    $label = json_decode($metric->label, true);
                    $labelDe = $label['de'] ?? '';
                    // Generate a slug-based key from the label
                    $baseKey = \Illuminate\Support\Str::slug($labelDe);
                    if (empty($baseKey)) {
                        $baseKey = 'metric-'.$metric->id;
                    }

                    $tileYearId = $metric->tile_year_id;

                    // Track keys per tile_year_id to handle duplicates
                    if (! isset($processedKeys[$tileYearId])) {
                        $processedKeys[$tileYearId] = [];
                    }

                    // If key already exists for this tile_year_id, append counter
                    $key = $baseKey;
                    if (isset($processedKeys[$tileYearId][$baseKey])) {
                        $counter = ++$processedKeys[$tileYearId][$baseKey];
                        $key = $baseKey.'-'.$counter;
                    } else {
                        $processedKeys[$tileYearId][$baseKey] = 1;
                    }

                    DB::table('metrics')
                        ->where('id', $metric->id)
                        ->update(['metric_key' => $key]);
                }
            });

        Schema::table('metrics', function (Blueprint $table) {
            // Make metric_key required and add unique constraint
            $table->string('metric_key')->nullable(false)->change();

            // Add unique constraint on tile_year_id + metric_key
            $table->unique(['tile_year_id', 'metric_key'], 'metrics_tile_year_id_metric_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            // Drop unique constraint
            $table->dropUnique('metrics_tile_year_id_metric_key_unique');

            // Drop metric_key column
            $table->dropColumn('metric_key');
        });
    }
};
