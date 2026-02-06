<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Intentionally empty. This migration once rewrote a broken default text;
     * made_with_text no longer has a default, and installs that ran the old
     * version keep their stored value.
     */
    public function up(): void
    {
        $newValue = 'Made with ❤️ from Ratisbona';
        $legacyValue = 'Made with  from Ratisbona';

        DB::table('settings')
            ->where('group', 'dashboard')
            ->where('name', 'made_with_text')
            ->where(function ($query) use ($legacyValue) {
                $query
                    ->whereNull('payload')
                    ->orWhere('payload', json_encode(''))
                    ->orWhere('payload', json_encode($legacyValue));
            })
            ->update(['payload' => json_encode($newValue)]);
    }
};
