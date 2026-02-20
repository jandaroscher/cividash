<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        // Remap Owner users → Admin
        $ownerRoles = DB::table('roles')->where('name', 'Owner')->get();
        foreach ($ownerRoles as $ownerRole) {
            $adminRole = DB::table('roles')
                ->where('name', 'Admin')
                ->where('tenant_id', $ownerRole->tenant_id)
                ->first();

            if ($adminRole) {
                // Move model_has_roles entries from Owner to Admin
                DB::table('model_has_roles')
                    ->where('role_id', $ownerRole->id)
                    ->update(['role_id' => $adminRole->id]);
            }
        }

        // Remap Viewer users → Redakteur (first rename Editor to Redakteur, then remap Viewer)
        // Step 1: Rename Editor → Redakteur
        DB::table('roles')->where('name', 'Editor')->update(['name' => 'Redakteur']);

        // Step 2: Remap Viewer users → Redakteur
        $viewerRoles = DB::table('roles')->where('name', 'Viewer')->get();
        foreach ($viewerRoles as $viewerRole) {
            $redakteurRole = DB::table('roles')
                ->where('name', 'Redakteur')
                ->where('tenant_id', $viewerRole->tenant_id)
                ->first();

            if ($redakteurRole) {
                DB::table('model_has_roles')
                    ->where('role_id', $viewerRole->id)
                    ->update(['role_id' => $redakteurRole->id]);
            }
        }

        // Delete Owner and Viewer roles
        $deletedRoleIds = DB::table('roles')
            ->whereIn('name', ['Owner', 'Viewer'])
            ->pluck('id');

        // Clean up any orphaned model_has_roles entries
        DB::table('model_has_roles')
            ->whereIn('role_id', $deletedRoleIds)
            ->delete();

        // Delete the roles themselves
        DB::table('roles')
            ->whereIn('name', ['Owner', 'Viewer'])
            ->delete();
    }

    public function down(): void
    {
        // Cannot reliably reverse role remapping
    }
};
