<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add time_granularity to tiles
        Schema::table('tiles', function (Blueprint $table) {
            $table->string('time_granularity', 10)->default('year')->after('position');
        });

        // 2. Rename tile_years -> time_periods
        Schema::rename('tile_years', 'time_periods');

        // 3. Add new columns to time_periods
        Schema::table('time_periods', function (Blueprint $table) {
            $table->string('granularity', 10)->default('year')->after('tile_id');
            $table->string('period_key', 20)->nullable()->after('granularity');
            $table->string('label', 30)->nullable()->after('period_key');
        });

        // 4. Backfill: convert integer year to period_key string.
        //    MySQL/MariaDB cast integers to char with CAST(... AS CHAR); PostgreSQL/SQLite use TEXT.
        $yearAsText = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? 'CAST(year AS CHAR)'
            : 'CAST(year AS TEXT)';
        DB::statement("UPDATE time_periods SET period_key = {$yearAsText}, granularity = 'year', label = {$yearAsText}");

        // 5. Make period_key not nullable and drop year column
        Schema::table('time_periods', function (Blueprint $table) {
            $table->string('period_key', 20)->nullable(false)->change();
            $table->dropColumn('year');
        });

        // 6. Add unique constraint and index
        Schema::table('time_periods', function (Blueprint $table) {
            $table->unique(['tile_id', 'period_key']);
            $table->index('granularity');
        });

        // 7. Rename FK in metric_values: tile_year_id -> time_period_id
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: simple column rename (no FK enforcement issues)
            Schema::table('metric_values', function (Blueprint $table) {
                $table->renameColumn('tile_year_id', 'time_period_id');
            });
        } elseif (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // PostgreSQL: drop dependent constraints/indexes via the portable
            // Schema Builder, rename the column, then recreate them. PostgreSQL has
            // no MySQL-style "DROP FOREIGN KEY"/"CHANGE", so we use Laravel's grammar.
            Schema::table('metric_values', function (Blueprint $table) {
                $table->dropForeign('metric_values_tile_year_id_foreign');
                $table->dropUnique('metric_values_metric_definition_id_tile_year_id_unique');
            });
            Schema::table('metric_values', function (Blueprint $table) {
                $table->renameColumn('tile_year_id', 'time_period_id');
            });
            Schema::table('metric_values', function (Blueprint $table) {
                $table->foreign('time_period_id', 'metric_values_time_period_id_foreign')
                    ->references('id')->on('time_periods')->cascadeOnDelete();
                $table->unique(['metric_definition_id', 'time_period_id'], 'metric_values_metric_definition_id_time_period_id_unique');
            });
        } else {
            // MariaDB/MySQL: must drop FK + unique + index atomically in one statement
            DB::statement('
                ALTER TABLE metric_values
                    DROP FOREIGN KEY metric_values_tile_year_id_foreign,
                    DROP INDEX metric_values_metric_definition_id_tile_year_id_unique,
                    DROP INDEX metric_values_tile_year_id_foreign,
                    CHANGE tile_year_id time_period_id BIGINT UNSIGNED NOT NULL,
                    ADD CONSTRAINT metric_values_time_period_id_foreign FOREIGN KEY (time_period_id) REFERENCES time_periods(id) ON DELETE CASCADE,
                    ADD UNIQUE metric_values_metric_definition_id_time_period_id_unique (metric_definition_id, time_period_id)
            ');
        }
    }

    public function down(): void
    {
        // Reverse FK rename in metric_values
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('metric_values', function (Blueprint $table) {
                $table->renameColumn('time_period_id', 'tile_year_id');
            });
        } elseif (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // PostgreSQL: mirror the up() approach using the portable Schema Builder.
            Schema::table('metric_values', function (Blueprint $table) {
                $table->dropForeign('metric_values_time_period_id_foreign');
                $table->dropUnique('metric_values_metric_definition_id_time_period_id_unique');
            });
            Schema::table('metric_values', function (Blueprint $table) {
                $table->renameColumn('time_period_id', 'tile_year_id');
            });
            Schema::table('metric_values', function (Blueprint $table) {
                $table->foreign('tile_year_id', 'metric_values_tile_year_id_foreign')
                    ->references('id')->on('time_periods')->cascadeOnDelete();
                $table->unique(['metric_definition_id', 'tile_year_id'], 'metric_values_metric_definition_id_tile_year_id_unique');
            });
        } else {
            DB::statement('
                ALTER TABLE metric_values
                    DROP FOREIGN KEY metric_values_time_period_id_foreign,
                    DROP INDEX metric_values_metric_definition_id_time_period_id_unique,
                    CHANGE time_period_id tile_year_id BIGINT UNSIGNED NOT NULL,
                    ADD CONSTRAINT metric_values_tile_year_id_foreign FOREIGN KEY (tile_year_id) REFERENCES time_periods(id) ON DELETE CASCADE,
                    ADD UNIQUE metric_values_metric_definition_id_tile_year_id_unique (metric_definition_id, tile_year_id)
            ');
        }

        // Reverse time_periods changes
        Schema::table('time_periods', function (Blueprint $table) {
            $table->dropUnique(['tile_id', 'period_key']);
            $table->dropIndex(['granularity']);
        });

        Schema::table('time_periods', function (Blueprint $table) {
            $table->integer('year')->nullable()->after('tile_id');
        });

        // Backfill year from period_key (only for 4-digit year values; non-year granularities get NULL).
        // Driver-specific: regex operator and integer cast differ between MySQL/MariaDB, PostgreSQL and SQLite.
        $downDriver = Schema::getConnection()->getDriverName();
        if (in_array($downDriver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // PostgreSQL: ~ for regex match, CAST AS INTEGER (no UNSIGNED type).
            DB::statement("UPDATE time_periods SET year = CASE WHEN period_key ~ '^[0-9]{4}$' THEN CAST(period_key AS INTEGER) ELSE NULL END");
        } elseif ($downDriver === 'sqlite') {
            // SQLite has no REGEXP by default; GLOB matches exactly four digits.
            DB::statement("UPDATE time_periods SET year = CASE WHEN period_key GLOB '[0-9][0-9][0-9][0-9]' THEN CAST(period_key AS INTEGER) ELSE NULL END");
        } else {
            // MySQL / MariaDB
            DB::statement("UPDATE time_periods SET year = CASE WHEN period_key REGEXP '^[0-9]{4}$' THEN CAST(period_key AS UNSIGNED) ELSE NULL END");
        }

        // Note: non-year granularities will have NULL year after rollback
        Schema::table('time_periods', function (Blueprint $table) {
            $table->dropColumn(['granularity', 'period_key', 'label']);
        });

        // Rename back
        Schema::rename('time_periods', 'tile_years');

        // Remove time_granularity from tiles
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropColumn('time_granularity');
        });
    }
};
