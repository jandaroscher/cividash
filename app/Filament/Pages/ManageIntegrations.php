<?php

namespace App\Filament\Pages;

use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Models\Tenant;
use App\Services\Integration\SyncResult;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ManageIntegrations extends Page implements HasForms
{
    use InteractsWithForms;

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

    protected function getViewData(): array
    {
        $syncStatus = $this->getSyncService()->getLastSyncStatus($this->getTenant());

        return [
            'apiUrl' => config('integrations.civitas.api_url'),
            'tokenUrl' => config('integrations.civitas.oauth.token_url'),
            'clientId' => config('integrations.civitas.oauth.client_id'),
            'hasSecret' => ! empty(config('integrations.civitas.oauth.client_secret')),
            'syncSchedule' => config('integrations.civitas.sync.schedule'),
            'batchSize' => config('integrations.civitas.sync.batch_size'),
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
                        $connected = app(ExternalDataSourceInterface::class)->isConnected();

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
