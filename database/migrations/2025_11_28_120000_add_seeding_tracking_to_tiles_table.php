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
            $table->timestamp('last_synced_at')->nullable()->after('updated_at');
            $table->string('source_hash', 64)->nullable()->after('last_synced_at')->comment('SHA256 hash of the source JSON data for this tile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropColumn(['last_synced_at', 'source_hash']);
        });
    }
};

