<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('category_groups', 'is_color_source')) {
            return;
        }

        if (! Schema::hasTable('settings')) {
            Schema::table('category_groups', function (Blueprint $table) {
                $table->dropColumn('is_color_source');
            });

            return;
        }

        // Migrate per-tenant color source settings
        $tenants = DB::table('tenants')->get();

        foreach ($tenants as $tenant) {
            $colorSourceGroup = DB::table('category_groups')
                ->where('is_color_source', true)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($colorSourceGroup) {
                DB::table('settings')->updateOrInsert(
                    [
                        'group' => 'branding',
                        'name' => 'tile_color_source_group_id',
                        'tenant_id' => $tenant->id,
                    ],
                    [
                        'payload' => json_encode($colorSourceGroup->id),
                        'locked' => false,
                    ]
                );
            }
        }

        // Also check global (tenant_id = NULL) category groups for default tenant
        $globalColorSource = DB::table('category_groups')
            ->where('is_color_source', true)
            ->whereNull('tenant_id')
            ->first();

        if ($globalColorSource) {
            $defaultTenant = DB::table('tenants')->where('slug', 'default')->first();

            if ($defaultTenant) {
                DB::table('settings')->updateOrInsert(
                    [
                        'group' => 'branding',
                        'name' => 'tile_color_source_group_id',
                        'tenant_id' => $defaultTenant->id,
                    ],
                    [
                        'payload' => json_encode($globalColorSource->id),
                        'locked' => false,
                    ]
                );
            }
        }

        // Drop the column
        Schema::table('category_groups', function (Blueprint $table) {
            $table->dropColumn('is_color_source');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('category_groups', 'is_color_source')) {
            return;
        }

        Schema::table('category_groups', function (Blueprint $table) {
            $table->boolean('is_color_source')->default(false)->after('is_filterable');
        });

        // Restore from settings
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = DB::table('settings')
            ->where('group', 'branding')
            ->where('name', 'tile_color_source_group_id')
            ->get();

        foreach ($settings as $setting) {
            $groupId = json_decode($setting->payload, true);
            if ($groupId) {
                DB::table('category_groups')
                    ->where('id', $groupId)
                    ->update(['is_color_source' => true]);
            }
        }
    }
};
