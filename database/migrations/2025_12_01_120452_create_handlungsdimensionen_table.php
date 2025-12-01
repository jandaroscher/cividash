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
        Schema::create('handlungsdimensionen', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // "grün", "gerecht", "produktiv"
            $table->json('title'); // translatable
            $table->string('icon')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handlungsdimensionen');
    }
};
