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
        Schema::create('sdg_ziele', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->unique(); // 1-17
            $table->json('title'); // translatable, vollständige Titel
            $table->string('icon')->nullable(); // SDG-icon-DE-{number}.svg
            $table->string('icon_en')->nullable(); // SDG-icon-EN-{number}.svg
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sdg_ziele');
    }
};
