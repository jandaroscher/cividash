<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `category_groups` table with its columns, defaults and constraints.
     *
     * The table includes: an auto-incrementing `id`; nullable `tenant_id` foreign key that cascades on delete;
     * `key` (string); `title` (JSON); `position` (integer, default 0); `is_filterable` (boolean, default false);
     * `is_color_source` (boolean, default false); `selection_type` (string, default "multi"); and timestamp columns.
     * Adds a composite unique index on (`tenant_id`, `key`).
     */
    public function up(): void
    {
        Schema::create('category_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('title');
            $table->integer('position')->default(0);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_color_source')->default(false);
            $table->string('selection_type')->default('multi');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    /**
     * Drop the `category_groups` table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_groups');
    }
};