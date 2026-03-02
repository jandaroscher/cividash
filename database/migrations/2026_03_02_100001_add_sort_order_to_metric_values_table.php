<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metric_values', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('metric_values', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
