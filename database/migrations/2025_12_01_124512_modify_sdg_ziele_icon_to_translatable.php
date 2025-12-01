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
        
        // Apply schema changes first
        Schema::table('sdg_ziele', function (Blueprint $table) {
            // Change icon column from string to json
            $table->json('icon')->nullable()->change();
            
            // Drop icon_en column
            $table->dropColumn('icon_en');
        });
        
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
        // Apply schema changes first
        Schema::table('sdg_ziele', function (Blueprint $table) {
            // Add icon_en column back
            $table->string('icon_en')->nullable()->after('icon');
            
            // Change icon back to string
            $table->string('icon')->nullable()->change();
        });
        
        // Then migrate data back after schema changes are applied
        $sdgZiele = DB::table('sdg_ziele')->get();
        
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
