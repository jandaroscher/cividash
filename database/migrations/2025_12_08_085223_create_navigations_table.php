<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `navigations` table and configure its schema.
     *
     * Creates a table named `navigations` with an `id` primary key, a nullable
     * JSON `navigation_items` column, boolean `show_language_switcher` (default
     * true), boolean `dropdown_enabled` (default false), and timestamp columns.
     *
     * Singleton behavior is enforced in application code via Navigation::getInstance()/getOrCreateInstance()
     * which always targets the record with id=1.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('navigations', function (Blueprint $table) {
            $table->id();
            $table->json('navigation_items')->nullable();
            $table->boolean('show_language_switcher')->default(true);
            $table->boolean('dropdown_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Drop the `navigations` table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigations');
    }
};