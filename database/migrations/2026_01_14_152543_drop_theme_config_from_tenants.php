<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the `theme_config` column from the `tenants` table.
     *
     * Removes the unused `theme_config` JSON column so the tenants schema no longer stores theme configuration.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('theme_config');
        });
    }

    /**
     * Recreates the `theme_config` column on the `tenants` table.
     *
     * Restores a nullable JSON column named `theme_config`.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('theme_config')->nullable();
        });
    }
};
