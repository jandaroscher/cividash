<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->json('hint')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropColumn('hint');
        });
    }
};
