<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tiles')) {
            return;
        }

        $tiles = DB::table('tiles')->whereNotNull('background_blocks')->get();

        foreach ($tiles as $tile) {
            $blocksValue = $tile->background_blocks;
            $blocksArray = is_string($blocksValue) ? json_decode($blocksValue, true) : $blocksValue;

            if (! is_array($blocksArray)) {
                continue;
            }

            // Already in locale structure
            if (isset($blocksArray['de']) || isset($blocksArray['en'])) {
                continue;
            }

            // Wrap existing blocks as German, empty English
            $updateData = json_encode([
                'de' => $blocksArray,
                'en' => [],
            ]);

            DB::table('tiles')
                ->where('id', $tile->id)
                ->update(['background_blocks' => $updateData]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tiles')) {
            return;
        }

        $tiles = DB::table('tiles')->whereNotNull('background_blocks')->get();

        foreach ($tiles as $tile) {
            $blocksValue = $tile->background_blocks;
            $blocksData = is_string($blocksValue) ? json_decode($blocksValue, true) : $blocksValue;

            if (is_array($blocksData) && isset($blocksData['de'])) {
                DB::table('tiles')
                    ->where('id', $tile->id)
                    ->update(['background_blocks' => json_encode($blocksData['de'])]);
            }
        }
    }
};
