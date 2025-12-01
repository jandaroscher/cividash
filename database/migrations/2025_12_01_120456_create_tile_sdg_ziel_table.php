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
        Schema::create('tile_sdg_ziel', function (Blueprint $table) {
            $table->foreignId('tile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sdg_ziel_id')->constrained('sdg_ziele')->cascadeOnDelete();
            $table->primary(['tile_id', 'sdg_ziel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tile_sdg_ziel');
    }
};
