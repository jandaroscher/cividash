<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add sponsors JSON column to footer_navigations table.
     *
     * Stores an array of sponsor entries (image path + optional link URL)
     * per locale, using Spatie HasTranslations.
     */
    public function up(): void
    {
        if (Schema::hasTable('footer_navigations') && ! Schema::hasColumn('footer_navigations', 'sponsors')) {
            Schema::table('footer_navigations', function (Blueprint $table) {
                $table->json('sponsors')->nullable()->after('copyright_text');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('footer_navigations') && Schema::hasColumn('footer_navigations', 'sponsors')) {
            Schema::table('footer_navigations', function (Blueprint $table) {
                $table->dropColumn('sponsors');
            });
        }
    }
};
