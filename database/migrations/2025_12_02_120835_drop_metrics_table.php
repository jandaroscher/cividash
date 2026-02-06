<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: Data is not migrated (manual migration chosen).
     * This migration should only be run after successful migration to the new structure.
     */
    public function up(): void
    {
        Schema::dropIfExists('metrics');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This would require recreating the old metrics table structure
        // For now, we'll leave this empty as the old structure is documented
        // in the original migration file
    }
};
