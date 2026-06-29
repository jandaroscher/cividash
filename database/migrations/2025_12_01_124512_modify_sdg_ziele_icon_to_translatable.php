<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, read existing data before schema changes
        $sdgZiele = DB::table('sdg_ziele')->get();
        $dataToMigrate = [];

        foreach ($sdgZiele as $sdg) {
            if ($sdg->icon || $sdg->icon_en) {
                $dataToMigrate[$sdg->id] = [
                    'de' => $sdg->icon ?? null,
                    'en' => $sdg->icon_en ?? null,
                ];
            }
        }

        // First, clear icon column to allow type change
        // We'll restore the data after schema change
        DB::table('sdg_ziele')->update(['icon' => null]);

        // Apply schema changes first.
        // SQLite cannot change a column type in place; PostgreSQL refuses an
        // implicit varchar->json cast (needs USING). Since the column was just
        // cleared above, both drop the column and re-add it as JSON instead of
        // using ->change(). MySQL/MariaDB can change the type directly.
        $recreateIconColumn = in_array(DB::getDriverName(), ['sqlite', 'pgsql', 'postgres', 'postgresql'], true);

        Schema::table('sdg_ziele', function (Blueprint $table) use ($recreateIconColumn) {
            if ($recreateIconColumn) {
                $table->dropColumn('icon');
            } else {
                $table->json('icon')->nullable()->change();
            }
            $table->dropColumn('icon_en');
        });

        if ($recreateIconColumn) {
            Schema::table('sdg_ziele', function (Blueprint $table) {
                $table->json('icon')->nullable();
            });
        }

        // Then migrate data after schema changes are applied
        foreach ($dataToMigrate as $id => $iconArray) {
            DB::table('sdg_ziele')
                ->where('id', $id)
                ->update(['icon' => json_encode($iconArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Read existing JSON icon data before dropping the column (the recreate
        // path below would otherwise lose it).
        $existingIcons = DB::table('sdg_ziele')->get(['id', 'icon']);

        // Apply schema changes first. As in up(), PostgreSQL cannot implicitly
        // cast json->varchar and SQLite cannot change types in place, so both
        // drop and re-add the column instead of using ->change().
        $recreateIconColumn = in_array(DB::getDriverName(), ['sqlite', 'pgsql', 'postgres', 'postgresql'], true);

        Schema::table('sdg_ziele', function (Blueprint $table) use ($recreateIconColumn) {
            $table->string('icon_en')->nullable()->after('icon');

            if ($recreateIconColumn) {
                $table->dropColumn('icon');
            } else {
                $table->string('icon')->nullable()->change();
            }
        });

        if ($recreateIconColumn) {
            Schema::table('sdg_ziele', function (Blueprint $table) {
                $table->string('icon')->nullable();
            });
        }

        // Then migrate data back after schema changes are applied
        $sdgZiele = $recreateIconColumn ? $existingIcons : DB::table('sdg_ziele')->get();

        foreach ($sdgZiele as $sdg) {
            if ($sdg->icon) {
                $iconArray = json_decode($sdg->icon, true);
                if (is_array($iconArray)) {
                    DB::table('sdg_ziele')
                        ->where('id', $sdg->id)
                        ->update([
                            'icon' => $iconArray['de'] ?? null,
                            'icon_en' => $iconArray['en'] ?? null,
                        ]);
                }
            }
        }
    }
};
