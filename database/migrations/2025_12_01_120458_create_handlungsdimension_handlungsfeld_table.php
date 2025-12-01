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
        Schema::create('handlungsdimension_handlungsfeld', function (Blueprint $table) {
            $table->foreignId('handlungsdimension_id')->constrained('handlungsdimensionen')->cascadeOnDelete();
            $table->foreignId('handlungsfeld_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['handlungsdimension_id', 'handlungsfeld_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handlungsdimension_handlungsfeld');
    }
};
