<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('phone');
            }
        });

        // Backfill: split name into first_name/last_name
        if (Schema::hasColumn('users', 'name')) {
            $users = DB::table('users')->whereNotNull('name')->get();

            foreach ($users as $user) {
                $parts = explode(' ', trim($user->name));
                $lastName = count($parts) > 1 ? array_pop($parts) : '';
                $firstName = implode(' ', $parts);

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $firstName ?: $user->name,
                    'last_name' => $lastName,
                ]);
            }
        }

        // Drop old columns
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
        });

        // Backfill name from first_name + last_name
        if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'name')) {
            DB::table('users')->get()->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                ]);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
