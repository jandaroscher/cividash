<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists (it may have been dropped in a later migration)
        if (Schema::hasTable('metrics')) {
            // Check if column already exists (in case migration was partially run)
            if (!Schema::hasColumn('metrics', 'indicator_type')) {
                Schema::table('metrics', function (Blueprint $table) {
                    // Add indicator_type column with default value 'small'
                    $table->string('indicator_type')->default('small')->after('icon');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if table exists before trying to drop column
        if (Schema::hasTable('metrics')) {
            // Check if column exists before trying to drop it
            if (Schema::hasColumn('metrics', 'indicator_type')) {
                Schema::table('metrics', function (Blueprint $table) {
                    // Drop indicator_type column
                    $table->dropColumn('indicator_type');
                });
            }
        }
    }
};

