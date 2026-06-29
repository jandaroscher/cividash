<?php

namespace App\Filament\Pages;

use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Models\Tenant;
use App\Services\Integration\NgsiLdClient;
use App\Services\Integration\SensorThingsClient;
use App\Services\Integration\SyncResult;
use App\Settings\IntegrationSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Illuminate\Contracts\Support\Htmlable;

class ManageIntegrations extends SettingsPage
{
    protected static string $settings = IntegrationSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.manage-integrations';

    protected static ?string $slug = 'integrations';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_integrations.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_integrations.title');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('integrations.civitas.enabled');
    }

    public static function canAccess(): bool
    {
        return (bool) config('integrations.civitas.enabled')
            && (bool) Filament::auth()->user()?->is_admin;
    }

    protected function getTenant(): Tenant
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        return $tenant;
    }

    protected function getSyncService(): SyncServiceInterface
    {
        return app(SyncServiceInterface::class);
    }

    /**
     * Build a data-source client from the values currently entered in the form,
     * so "Test connection" validates unsaved edits rather than the persisted
     * configuration. Driver-aware: ngsi-ld (default) or sensorthings.
     */
    protected function buildClientFromFormState(): ExternalDataSourceInterface
    {
        $state = $this->form->getState();

        $overrides = array_filter([
            'api_url' => $state['api_url'] ?? null,
            'oauth_token_url' => $state['oauth_token_url'] ?? null,
            'oauth_client_id' => $state['oauth_client_id'] ?? null,
            // The secret field is intentionally blank in the form; when the
            // user leaves it empty we fall back to the stored secret, mirroring
            // the save behaviour (dehydrateStateUsing).
            'oauth_client_secret' => filled($state['oauth_client_secret'] ?? null)
                ? $state['oauth_client_secret']
                : app(IntegrationSettings::class)->oauth_client_secret,
        ], fn ($value) => filled($value));

        return config('integrations.civitas.driver') === 'sensorthings'
            ? SensorThingsClient::fromConfig($overrides)
            : NgsiLdClient::fromConfig($overrides);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.pages.manage_integrations.config_heading'))
                    ->description(__('filament.pages.manage_integrations.config_description'))
                    ->schema([
                        TextInput::make('api_url')
                            ->label(__('filament.pages.manage_integrations.api_url'))
                            ->url()
                            ->placeholder('https://core.example.com/FROST-Server/v1.1'),

                        TextInput::make('oauth_token_url')
                            ->label(__('filament.pages.manage_integrations.oauth_token_url'))
                            ->url()
                            ->placeholder('https://keycloak.example.com/realms/civitas/protocol/openid-connect/token'),

                        TextInput::make('oauth_client_id')
                            ->label(__('filament.pages.manage_integrations.client_id'))
                            ->placeholder('dashboard-client'),

                        TextInput::make('oauth_client_secret')
                            ->label(__('filament.pages.manage_integrations.client_secret'))
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state, IntegrationSettings $settings) => filled($state) ? $state : $settings->oauth_client_secret)
                            ->placeholder(__('filament.pages.manage_integrations.secret_placeholder')),

                        Select::make('sync_schedule')
                            ->label(__('filament.pages.manage_integrations.sync_schedule'))
                            ->options([
                                'hourly' => __('filament.pages.manage_integrations.schedule_hourly'),
                                'daily' => __('filament.pages.manage_integrations.schedule_daily'),
                                'weekly' => __('filament.pages.manage_integrations.schedule_weekly'),
                            ])
                            ->default('daily'),

                        TextInput::make('sync_batch_size')
                            ->label(__('filament.pages.manage_integrations.batch_size'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(100),
                    ])
                    ->columns(2),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Never send the secret to the frontend — show empty field with placeholder
        $data['oauth_client_secret'] = null;

        return $data;
    }

    protected function getViewData(): array
    {
        $syncStatus = $this->getSyncService()->getLastSyncStatus($this->getTenant());

        return [
            'syncStatus' => $syncStatus,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(__('filament.pages.manage_integrations.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (): void {
                    try {
                        // Test the configuration currently typed into the form
                        // (not just the persisted settings), so editing the URL
                        // or credentials and clicking Test before saving tests
                        // the new values.
                        $connected = $this->buildClientFromFormState()->isConnected();

                        if ($connected) {
                            Notification::make()
                                ->title(__('filament.pages.manage_integrations.test_connection_success'))
                                ->body(__('filament.pages.manage_integrations.test_connection_success_body'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('filament.pages.manage_integrations.test_connection_failed'))
                                ->body(__('filament.pages.manage_integrations.test_connection_failed_body'))
                                ->danger()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('filament.pages.manage_integrations.test_connection_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('syncNow')
                ->label(__('filament.pages.manage_integrations.sync_now'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('filament.pages.manage_integrations.sync_confirm_heading'))
                ->modalDescription(__('filament.pages.manage_integrations.sync_confirm_description'))
                ->modalSubmitActionLabel(__('filament.pages.manage_integrations.sync_confirm_submit'))
                ->action(function (): void {
                    try {
                        $result = $this->getSyncService()->syncAll($this->getTenant());

                        $this->sendSyncResultNotification($result);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('filament.pages.manage_integrations.sync_error'))
                            ->body(__('filament.pages.manage_integrations.sync_error_body', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function sendSyncResultNotification(SyncResult $result): void
    {
        $body = __('filament.pages.manage_integrations.sync_success_body', [
            'created' => $result->created,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
        ]);

        if ($result->hasErrors()) {
            Notification::make()
                ->title(__('filament.pages.manage_integrations.sync_warning'))
                ->body($body)
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title(__('filament.pages.manage_integrations.sync_success'))
                ->body($body)
                ->success()
                ->send();
        }
    }
}
