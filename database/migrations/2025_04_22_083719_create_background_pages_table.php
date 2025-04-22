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
        Schema::create('background_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->longText('content');
            $table->integer('position')->default(0);
            $table->timestamps();
            // tile_id kommt per eigener Migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('background_pages');
    }
};
