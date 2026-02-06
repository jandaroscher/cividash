<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add tenant-scoped foreign key columns to multiple domain tables and a default tenant reference on users.
     *
     * Adds a nullable `default_tenant_id` column to the `users` table that references `tenants.id` and is set to null on tenant deletion; adds nullable `tenant_id` columns referencing `tenants.id` with cascade-on-delete to the following tables: `categories`, `tiles`, `tile_years`, `sdg_ziele`, `handlungsdimensionen`, `navigations`, `footer_navigations`, `metric_definitions`, `metric_values`, `background_pages`, and the pages table resolved via `config('filament-fabricator.table_name', 'pages')`. Also adds `tenant_id` to `metrics` only if the `metrics` table exists.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_tenant_id')->nullable()->after('remember_token')->constrained('tenants')->nullOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('tiles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('tile_years', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('sdg_ziele', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('handlungsdimensionen', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('navigations', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('footer_navigations', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('metric_definitions', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('metric_values', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        if (Schema::hasTable('metrics')) {
            Schema::table('metrics', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('background_pages', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table(config('filament-fabricator.table_name', 'pages'), function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Drop tenant-related foreign key columns from application tables.
     *
     * Removes the constrained foreign id columns used for tenant scoping:
     * - `tenant_id` from the configured pages table, `background_pages`, `metric_values`,
     *   `metric_definitions`, `footer_navigations`, `navigations`, `handlungsdimensionen`,
     *   `sdg_ziele`, `tile_years`, `tiles`, and `categories`.
     * - `default_tenant_id` from `users`.
     *
     * The `metrics` table is handled conditionally and its `tenant_id` column is dropped only if the table exists.
     */
    public function down(): void
    {
        Schema::table(config('filament-fabricator.table_name', 'pages'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('background_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('metric_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        if (Schema::hasTable('metrics')) {
            Schema::table('metrics', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::table('metric_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('footer_navigations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('navigations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('handlungsdimensionen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('sdg_ziele', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('tile_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('tiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_tenant_id');
        });
    }
};
