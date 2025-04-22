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
        Schema::create('tile_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tile_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->integer('year');
            $table->timestamps();
            $table->integer('sort')->default(0)->after('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tile_years');
    }
};
