<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_active boolean field to categories and category_groups tables.
     *
     * This enables consistent visibility control across all content management
     * resources (Pages, Tiles, Categories, CategoryGroups).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('color');
        });

        Schema::table('category_groups', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('selection_type');
        });
    }

    /**
     * Revert the migration by removing the `is_active` columns from the `categories` and `category_groups` tables.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('category_groups', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};