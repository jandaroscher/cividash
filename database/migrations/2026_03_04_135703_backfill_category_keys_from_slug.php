<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backfill empty category keys from the German slug (title).
     */
    public function up(): void
    {
        $categories = DB::table('categories')
            ->whereNull('key')
            ->orWhere('key', '')
            ->get(['id', 'slug']);

        foreach ($categories as $category) {
            $slug = $category->slug;

            // Extract German title from JSON or use plain string
            $title = $slug;
            if (is_string($slug)) {
                $decoded = json_decode($slug, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $title = $decoded['de'] ?? $decoded['en'] ?? reset($decoded) ?: $slug;
                }
            }

            $key = Str::slug((string) $title);

            if (filled($key)) {
                DB::table('categories')
                    ->where('id', $category->id)
                    ->update(['key' => $key]);
            }
        }
    }

    /**
     * Reverse is not applicable — keys were missing before.
     */
    public function down(): void
    {
        // No rollback: keys were empty/null before, restoring that state is not useful.
    }
};
