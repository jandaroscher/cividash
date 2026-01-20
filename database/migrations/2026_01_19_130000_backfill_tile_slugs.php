<?php

use App\Models\Tile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tiles', 'slug')) {
            return;
        }

        $locales = config('app.available_locales', ['de', 'en']);
        if (! is_array($locales) || $locales === []) {
            $locales = ['de', 'en'];
        }

        Tile::query()->each(function (Tile $tile) use ($locales) {
            $slugs = is_array($tile->slug) ? $tile->slug : [];
            $changed = false;

            foreach ($locales as $locale) {
                $current = isset($slugs[$locale]) ? trim((string) $slugs[$locale]) : '';
                if ($current !== '') {
                    continue;
                }

                $title = $tile->getTranslation('title', $locale, false)
                    ?: $tile->getTranslation('title', 'de', false);

                if ($title) {
                    $slugs[$locale] = Str::slug($title);
                    $changed = true;
                }
            }

            if ($changed) {
                $tile->slug = $slugs;
                $tile->save();
            }
        });
    }

    public function down(): void
    {
        // No rollback for backfilled slugs.
    }
};
