<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class IntegrationSettings extends Settings
{
    public ?string $api_url = null;

    public ?string $oauth_token_url = null;

    public ?string $oauth_client_id = null;

    public ?string $oauth_client_secret = null;

    public string $sync_schedule = 'daily';

    public int $sync_batch_size = 100;

    public static function group(): string
    {
        return 'integration';
    }
}
