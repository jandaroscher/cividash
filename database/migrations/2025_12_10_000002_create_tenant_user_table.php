<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the tenant_user pivot table for associating tenants with users.
     *
     * The table includes an auto-incrementing primary key `id`, foreign keys
     * `tenant_id` and `user_id` (both constrained and set to cascade on delete),
     * standard timestamp columns, and a unique composite index on `tenant_id` and `user_id`.
     */
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    /**
     * Drop the tenant_user table if it exists.
     *
     * This reverses the migration by removing the tenant_user table from the database.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
