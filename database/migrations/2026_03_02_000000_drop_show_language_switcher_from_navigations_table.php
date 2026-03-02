<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('navigations', 'show_language_switcher')) {
            Schema::table('navigations', function (Blueprint $table) {
                $table->dropColumn('show_language_switcher');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('navigations', 'show_language_switcher')) {
            Schema::table('navigations', function (Blueprint $table) {
                $table->boolean('show_language_switcher')->default(true)->after('navigation_items');
            });
        }
    }
};
