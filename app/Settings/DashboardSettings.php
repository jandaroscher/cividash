<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class DashboardSettings extends Settings
{
    public ?string $open_source_docs_url;
    public ?string $user_manual_url;
    public ?string $contact_name;
    public ?string $contact_email;
    public ?string $contact_url;
    public bool $show_server_time;
    public ?string $made_with_text;

    /**
     * Get the settings group name used to register these settings.
     *
     * @return string The settings group identifier "dashboard".
     */
    public static function group(): string
    {
        return 'dashboard';
    }
}