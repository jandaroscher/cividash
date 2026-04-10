<?php

namespace Tests\Feature\Filament\Settings;

use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Filament\Pages\ManageIntegrations;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use App\Services\Integration\SyncResult;
use App\Services\Integration\SyncStatus;
use App\Settings\IntegrationSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);

        config(['integrations.civitas.enabled' => true]);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_page_renders_for_admin(): void
    {
        Livewire::test(ManageIntegrations::class)
            ->assertSuccessful();
    }

    public function test_page_not_accessible_for_non_admin(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $this->actingAs($nonAdmin);

        Livewire::test(ManageIntegrations::class)
            ->assertForbidden();
    }

    public function test_page_hidden_when_civitas_disabled(): void
    {
        config(['integrations.civitas.enabled' => false]);

        $this->assertFalse(ManageIntegrations::shouldRegisterNavigation());
    }

    public function test_page_visible_when_civitas_enabled(): void
    {
        config(['integrations.civitas.enabled' => true]);

        $this->assertTrue(ManageIntegrations::shouldRegisterNavigation());
    }

    public function test_page_not_accessible_when_civitas_disabled(): void
    {
        config(['integrations.civitas.enabled' => false]);

        Livewire::test(ManageIntegrations::class)
            ->assertForbidden();
    }

    public function test_form_saves_settings(): void
    {
        Livewire::test(ManageIntegrations::class)
            ->fillForm([
                'api_url' => 'https://core.example.com/FROST-Server/v1.1',
                'oauth_token_url' => 'https://keycloak.example.com/token',
                'oauth_client_id' => 'dashboard-client',
                'oauth_client_secret' => 'my-secret',
                'sync_schedule' => 'hourly',
                'sync_batch_size' => 50,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(IntegrationSettings::class);
        $this->assertEquals('https://core.example.com/FROST-Server/v1.1', $settings->api_url);
        $this->assertEquals('https://keycloak.example.com/token', $settings->oauth_token_url);
        $this->assertEquals('dashboard-client', $settings->oauth_client_id);
        $this->assertEquals('my-secret', $settings->oauth_client_secret);
        $this->assertEquals('hourly', $settings->sync_schedule);
        $this->assertEquals(50, $settings->sync_batch_size);
    }

    public function test_secret_not_exposed_in_form(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->oauth_client_secret = 'super-secret-value';
        $settings->save();

        Livewire::test(ManageIntegrations::class)
            ->assertFormFieldExists('oauth_client_secret')
            ->assertDontSee('super-secret-value');
    }

    public function test_empty_secret_preserves_existing(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->oauth_client_secret = 'original-secret';
        $settings->save();

        Livewire::test(ManageIntegrations::class)
            ->fillForm([
                'oauth_client_secret' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings->refresh();
        $this->assertEquals('original-secret', $settings->oauth_client_secret);
    }

    public function test_connection_test_success(): void
    {
        $this->mock(ExternalDataSourceInterface::class)
            ->shouldReceive('isConnected')->andReturn(true);

        Livewire::test(ManageIntegrations::class)
            ->callAction('testConnection')
            ->assertNotified(__('filament.pages.manage_integrations.test_connection_success'));
    }

    public function test_connection_test_failure(): void
    {
        $this->mock(ExternalDataSourceInterface::class)
            ->shouldReceive('isConnected')->andReturn(false);

        Livewire::test(ManageIntegrations::class)
            ->callAction('testConnection')
            ->assertNotified(__('filament.pages.manage_integrations.test_connection_failed'));
    }

    public function test_sync_action_calls_service(): void
    {
        $syncResult = new SyncResult(created: 2, updated: 1, skipped: 5, failed: 0);

        $mock = $this->mock(SyncServiceInterface::class);
        $mock->shouldReceive('getLastSyncStatus')
            ->andReturn(new SyncStatus(isConfigured: true));
        $mock->shouldReceive('syncAll')
            ->once()
            ->withArgs(fn (Tenant $tenant) => $tenant->id === $this->tenant->id)
            ->andReturn($syncResult);

        Livewire::test(ManageIntegrations::class)
            ->callAction('syncNow')
            ->assertNotified(__('filament.pages.manage_integrations.sync_success'));
    }

    public function test_sync_status_shows_last_sync_date(): void
    {
        $tile = Tile::factory()->create(['tenant_id' => $this->tenant->id]);
        $tile->forceFill([
            'external_source' => 'civitas-core',
            'external_id' => 'thing-1',
            'last_synced_at' => '2026-04-10 12:00:00',
        ])->save();

        Livewire::test(ManageIntegrations::class)
            ->assertDontSee(__('filament.pages.manage_integrations.never_synced'));
    }
}
