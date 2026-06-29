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
        Schema::table('metric_values', function (Blueprint $table) {
            $table->decimal('value', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('metric_values')
            ->whereNull('value')
            ->update(['value' => 0]);

        Schema::table('metric_values', function (Blueprint $table) {
            $table->decimal('value', 15, 2)->default(0)->nullable(false)->change();
        });
    }
};
