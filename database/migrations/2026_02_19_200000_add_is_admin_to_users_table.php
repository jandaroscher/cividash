<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_active');
        });

        // Backfill from existing Spatie Admin roles
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            $adminUserIds = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'Admin')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->distinct()
                ->pluck('model_has_roles.model_id');

            if ($adminUserIds->isNotEmpty()) {
                DB::table('users')->whereIn('id', $adminUserIds)->update(['is_admin' => true]);

                // Remove Admin role assignments from Spatie (keep Redakteur)
                $adminRoleIds = DB::table('roles')->where('name', 'Admin')->pluck('id');
                DB::table('model_has_roles')
                    ->whereIn('role_id', $adminRoleIds)
                    ->where('model_type', 'App\\Models\\User')
                    ->delete();

                // Remove admin users from tenant_user pivot (admins bypass tenant checks now)
                DB::table('tenant_user')->whereIn('user_id', $adminUserIds)->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
