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
        Schema::create('metric_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tile_id')
                ->constrained('tiles')
                ->cascadeOnDelete();
            $table->string('metric_key');
            $table->json('label');
            $table->json('unit')->nullable();
            $table->string('icon')->nullable();
            $table->string('indicator_type')->default('small');
            $table->timestamps();

            // Unique constraint: metric_key is unique per tile
            $table->unique(['tile_id', 'metric_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_definitions');
    }
};

