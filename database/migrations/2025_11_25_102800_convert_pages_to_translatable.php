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
        $tableName = config('filament-fabricator.table_name', 'pages');

        // Step 1: Schema changes
        // Drop the composite unique constraint only if it actually exists.
        // NOTE: On PostgreSQL a failed statement aborts the surrounding migration
        // transaction, so we must NOT rely on try/catch to swallow a missing-index
        // error — that would poison every subsequent statement in this migration.
        // An explicit existence check keeps the transaction healthy on all drivers.
        if ($this->hasIndex($tableName, ['slug', 'parent_id'], 'pages_slug_parent_id_unique')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['slug', 'parent_id']);
            });
        }

        // Add meta_description column as JSON only if it doesn't exist
        if (! Schema::hasColumn($tableName, 'meta_description')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('meta_description')->nullable()->after('blocks');
            });
        }

        // Step 2: Migrate existing data: convert string values to JSON format
        // Do this BEFORE changing column types to avoid type conversion issues
        $pages = DB::table($tableName)->get();

        foreach ($pages as $page) {
            $updateData = [];

            // Convert title from string to JSON string
            if (! empty($page->title)) {
                $titleValue = $page->title;
                // Check if it's already valid JSON with locale structure
                if ($this->isJson($titleValue)) {
                    $decoded = json_decode($titleValue, true);
                    // Only skip if it's already in the correct locale structure
                    if (! is_array($decoded) || (! isset($decoded['de']) && ! isset($decoded['en']))) {
                        // Decode the JSON first, then wrap the decoded value in locale structure
                        // Use the decoded value (could be array, string, number, etc.)
                        $updateData['title'] = json_encode(['de' => $decoded, 'en' => '']);
                    }
                } else {
                    // It's a plain string, convert to JSON with locale structure
                    $updateData['title'] = json_encode(['de' => $titleValue, 'en' => '']);
                }
            }

            // Convert slug from string to JSON string
            if (! empty($page->slug)) {
                $slugValue = $page->slug;
                // Check if it's already valid JSON with locale structure
                if ($this->isJson($slugValue)) {
                    $decoded = json_decode($slugValue, true);
                    // Only skip if it's already in the correct locale structure
                    if (! is_array($decoded) || (! isset($decoded['de']) && ! isset($decoded['en']))) {
                        // Decode the JSON first, then wrap the decoded value in locale structure
                        // Use the decoded value (could be array, string, number, etc.)
                        $updateData['slug'] = json_encode(['de' => $decoded, 'en' => '']);
                    }
                } else {
                    // It's a plain string, convert to JSON with locale structure
                    $updateData['slug'] = json_encode(['de' => $slugValue, 'en' => '']);
                }
            }

            // Convert blocks from JSON Array to JSON {"de": [...], "en": []}
            if (! empty($page->blocks)) {
                // blocks is already JSON, but might be array or string
                $blocksValue = $page->blocks;
                $blocksArray = is_string($blocksValue) ? json_decode($blocksValue, true) : $blocksValue;

                // Check if it's already in locale structure
                if (is_array($blocksArray) && ! isset($blocksArray['de']) && ! isset($blocksArray['en'])) {
                    // It's an array of blocks, wrap it in locale structure
                    $updateData['blocks'] = json_encode([
                        'de' => $blocksArray,
                        'en' => [],
                    ]);
                } elseif (! is_array($blocksArray)) {
                    // Something went wrong, create empty structure
                    $updateData['blocks'] = json_encode(['de' => [], 'en' => []]);
                }
            }

            if (! empty($updateData)) {
                DB::table($tableName)
                    ->where('id', $page->id)
                    ->update($updateData);
            }
        }

        // Step 3: Change column types from string to json.
        // The JSON strings written above are now valid and will be stored correctly.
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // PostgreSQL: keep title/slug as varchar. The json type has no default
            // btree operator class (pages keeps a btree index on title, and slug is
            // referenced by unique constraints elsewhere), and switching to json
            // would also reject inserts of legacy plain-string values. The stored
            // values are valid JSON text and are read back with CAST(... AS json) at
            // query time (see App\Traits\BuildsJsonLocaleExpressions / the
            // whereTranslation() query macro), so no column retype is needed.
        } elseif ($driver === 'sqlite') {
            // SQLite stores JSON as text and has no rigid column types, so leave the
            // columns as-is; the data is already valid JSON.
        } else {
            // MySQL / MariaDB: a direct type change is sufficient.
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->json('title')->change();
                    $table->json('slug')->change();
                });
            } catch (Exception $e) {
                // Columns are already json/longtext - that's fine.
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('filament-fabricator.table_name', 'pages');

        // Migrate data back: extract 'de' value from JSON.
        // On PostgreSQL/SQLite title/slug stayed varchar (see up()), so the same
        // PHP-side conversion works across all drivers.
        $pages = DB::table($tableName)->get();

        foreach ($pages as $page) {
            $updateData = [];

            // Extract 'de' value from title JSON
            if (! empty($page->title)) {
                $titleData = is_string($page->title) ? json_decode($page->title, true) : $page->title;
                if (is_array($titleData) && isset($titleData['de'])) {
                    $deValue = $titleData['de'];
                    // If the value is not a string, encode it to JSON to prevent data loss
                    $updateData['title'] = is_string($deValue) ? $deValue : json_encode($deValue);
                }
            }

            // Extract 'de' value from slug JSON.
            if (! empty($page->slug)) {
                $slugData = is_string($page->slug) ? json_decode($page->slug, true) : $page->slug;
                if (is_array($slugData) && isset($slugData['de'])) {
                    $deValue = $slugData['de'];
                    // If the value is not a string, encode it to JSON to prevent data loss
                    $updateData['slug'] = is_string($deValue) ? $deValue : json_encode($deValue);
                }
            }

            // Extract 'de' value from blocks JSON
            if (! empty($page->blocks)) {
                $blocksData = is_string($page->blocks) ? json_decode($page->blocks, true) : $page->blocks;
                if (is_array($blocksData) && isset($blocksData['de'])) {
                    $updateData['blocks'] = json_encode($blocksData['de']);
                }
            }

            if (! empty($updateData)) {
                DB::table($tableName)->where('id', $page->id)->update($updateData);
            }
        }

        $driver = DB::getDriverName();
        $titleSlugAreJson = ! in_array($driver, ['pgsql', 'postgres', 'postgresql', 'sqlite'], true);

        Schema::table($tableName, function (Blueprint $table) use ($titleSlugAreJson) {
            if ($titleSlugAreJson) {
                // MySQL/MariaDB: title/slug were converted to json in up(); revert them.
                $table->string('title')->change();
                $table->string('slug')->change();
            }

            // Restore composite unique constraint and drop the added column.
            $table->unique(['slug', 'parent_id']);
            $table->dropColumn('meta_description');
        });
    }

    /**
     * Check whether a table has an index, matched either by its exact name or by
     * the set of columns it covers. Works across MySQL/MariaDB, PostgreSQL and
     * SQLite without throwing (so it is safe inside a PostgreSQL transaction).
     *
     * @param  array<int, string>  $columns
     */
    private function hasIndex(string $table, array $columns, string $indexName): bool
    {
        try {
            $sortedColumns = $columns;
            sort($sortedColumns);

            foreach (Schema::getIndexes($table) as $index) {
                if (($index['name'] ?? null) === $indexName) {
                    return true;
                }

                $indexColumns = $index['columns'] ?? [];
                sort($indexColumns);

                if ($indexColumns === $sortedColumns) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            // If index introspection is unavailable, assume it is absent.
        }

        return false;
    }

    /**
     * Check if a string is valid JSON.
     */
    private function isJson(?string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
};
