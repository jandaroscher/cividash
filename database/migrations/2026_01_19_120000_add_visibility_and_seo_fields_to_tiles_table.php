<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'tiles';

        if (! Schema::hasColumn($tableName, 'slug')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('slug')->nullable()->after('title');
            });
        }

        if (! Schema::hasColumn($tableName, 'is_public')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_public')->default(true)->index()->after('position');
            });
        }

        if (! Schema::hasColumn($tableName, 'meta_description')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('meta_description')->nullable()->after('description');
            });
        }

        if (! Schema::hasColumn($tableName, 'meta_title')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('meta_title')->nullable()->after('meta_description');
            });
        }

        if (! Schema::hasColumn($tableName, 'meta_image')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('meta_image')->nullable()->after('meta_title');
            });
        }
    }

    public function down(): void
    {
        $tableName = 'tiles';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'meta_image')) {
                $table->dropColumn('meta_image');
            }

            if (Schema::hasColumn($tableName, 'meta_title')) {
                $table->dropColumn('meta_title');
            }

            if (Schema::hasColumn($tableName, 'meta_description')) {
                $table->dropColumn('meta_description');
            }

            if (Schema::hasColumn($tableName, 'is_public')) {
                $table->dropColumn('is_public');
            }

            if (Schema::hasColumn($tableName, 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
