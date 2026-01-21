<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modify the categories table to add grouping and styling fields and adjust indexes.
     *
     * Adds a nullable foreign key `category_group_id` (references `category_groups`, set to null on delete),
     * adds nullable `key` and `color` string columns, drops the unique `slug` index, and creates an index on `category_group_id`.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('category_group_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('category_groups')
                ->nullOnDelete();
            $table->string('key')->nullable()->after('category_group_id');
            $table->string('color')->nullable()->after('icon');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_slug_unique');
            $table->index('category_group_id');
        });
    }

    / **
     * Reverts schema modifications on the categories table: removes the index and foreign key for `category_group_id`, restores the unique `slug` constraint, and drops the `key` and `color` columns.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['category_group_id']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('category_group_id');
            $table->dropColumn('key');
            $table->dropColumn('color');
        });
    }
};