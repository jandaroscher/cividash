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
        Schema::table('tiles', function (Blueprint $table) {
            $table->foreignId('handlungsdimension_id')->nullable()->after('position')->constrained('handlungsdimensionen')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropForeign(['handlungsdimension_id']);
            $table->dropColumn('handlungsdimension_id');
        });
    }
};
