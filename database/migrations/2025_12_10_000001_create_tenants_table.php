<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `tenants` database table with its columns and indexes.
     *
     * The table includes an auto-incrementing `id`, `name` (string), `slug` (unique string),
     * `theme_config` (JSON, nullable), and the standard `created_at`/`updated_at` timestamps.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('theme_config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drops the `tenants` table if it exists.
     *
     * This reverses the migration by removing the tenants table from the database.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
