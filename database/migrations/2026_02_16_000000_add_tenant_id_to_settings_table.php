<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Use sentinel value 0 for global settings instead of NULL.
            // This ensures the composite unique index properly prevents duplicate
            // global rows (NULL != NULL in SQL, but 0 == 0).
            // No FK constraint — tenant cleanup is handled by DashboardResetCommand.
            $table->unsignedBigInteger('tenant_id')->default(0)->after('id');

            // Replace the old unique index with one that includes tenant_id
            $table->dropUnique(['group', 'name']);
            $table->unique(['group', 'name', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['group', 'name', 'tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['group', 'name']);
        });
    }
};
