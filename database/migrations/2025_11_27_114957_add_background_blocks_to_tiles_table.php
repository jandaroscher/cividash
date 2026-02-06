<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable JSON column `background_blocks` to the `tiles` table after `position`.
     *
     * The new column stores background block data and is nullable.
     */
    public function up(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->json('background_blocks')->nullable()->after('position');
        });
    }

    /**
     * Remove the `background_blocks` column from the `tiles` table.
     */
    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropColumn('background_blocks');
        });
    }
};
