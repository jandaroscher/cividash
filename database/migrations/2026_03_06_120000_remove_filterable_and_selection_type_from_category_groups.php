<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_groups', function (Blueprint $table) {
            $table->dropColumn(['is_filterable', 'selection_type']);
        });
    }

    public function down(): void
    {
        Schema::table('category_groups', function (Blueprint $table) {
            $table->boolean('is_filterable')->default(true)->after('position');
            $table->string('selection_type')->default('multi')->after('is_filterable');
        });
    }
};
