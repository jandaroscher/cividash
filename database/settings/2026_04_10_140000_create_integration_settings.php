<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integration.api_url', config('integrations.civitas.api_url'));
        $this->migrator->add('integration.oauth_token_url', config('integrations.civitas.oauth.token_url'));
        $this->migrator->add('integration.oauth_client_id', config('integrations.civitas.oauth.client_id'));
        $this->migrator->add('integration.oauth_client_secret', config('integrations.civitas.oauth.client_secret'));
        $this->migrator->add('integration.sync_schedule', config('integrations.civitas.sync.schedule', 'daily'));
        $this->migrator->add('integration.sync_batch_size', (int) config('integrations.civitas.sync.batch_size', 100));
    }
};
