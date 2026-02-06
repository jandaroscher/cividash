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
        Schema::create('metric_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_definition_id')
                ->constrained('metric_definitions')
                ->cascadeOnDelete();
            $table->foreignId('tile_year_id')
                ->constrained('tile_years')
                ->cascadeOnDelete();
            $table->decimal('value', 15, 2);
            $table->timestamps();

            // Unique constraint: one value per metric definition per year
            $table->unique(['metric_definition_id', 'tile_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_values');
    }
};
