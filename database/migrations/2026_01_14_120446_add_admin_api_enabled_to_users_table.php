<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add admin_api_enabled flag to users table for minimal admin API permission control.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('admin_api_enabled')->default(false)->after('remember_token');
        });
    }

    /**
     * Reverts the migration by dropping the `admin_api_enabled` column from the `users` table.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_api_enabled');
        });
    }
};
