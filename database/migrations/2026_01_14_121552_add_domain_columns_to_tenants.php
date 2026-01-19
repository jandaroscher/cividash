<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `domain` and `frontend_base_url` columns to the `tenants` table.
     *
     * `domain` is a nullable, unique string used to resolve a tenant by request host and is added after `slug`.
     * `frontend_base_url` is a nullable string used for CORS and API responses and is added after `domain`.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain')->nullable()->unique()->after('slug');
            $table->string('frontend_base_url')->nullable()->after('domain');
        });
    }

    /**
     * Reverts tenant table changes by removing the unique constraint on `domain` and dropping the `domain` and `frontend_base_url` columns.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropColumn(['domain', 'frontend_base_url']);
        });
    }
};