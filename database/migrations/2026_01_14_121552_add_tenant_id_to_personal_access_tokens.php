<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable tenant_id column and foreign key on the personal_access_tokens table.
     *
     * Adds a nullable `tenant_id` column placed after `tokenable_id`, constrains it to the `tenants` table
     * with cascade-on-delete behavior, and creates an index. The column is nullable for backward compatibility
     * with existing tokens.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverts the migration by removing tenant_id and its associated constraints from the personal_access_tokens table.
     *
     * Removes the foreign key constraint on `tenant_id`, drops the index on `tenant_id`, and drops the `tenant_id` column.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};