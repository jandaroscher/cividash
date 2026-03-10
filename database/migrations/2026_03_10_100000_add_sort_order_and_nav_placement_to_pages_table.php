<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('parent_id');
            $table->string('nav_placement', 20)->default('none')->after('sort_order');
        });

        // Backfill sort_order based on created_at
        $pages = DB::table('pages')->orderBy('created_at')->get();
        foreach ($pages as $index => $page) {
            DB::table('pages')->where('id', $page->id)->update(['sort_order' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'nav_placement']);
        });
    }
};
