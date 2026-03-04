<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Services\ApiTokenService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ManageApiKeys extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 25;

    protected static string $view = 'filament.pages.manage-api-keys';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    /**
     * The newly created token's plain text value (shown once after creation).
     */
    public ?string $newTokenPlainText = null;

    /**
     * The newly created token's name (for display).
     */
    public ?string $newTokenName = null;

    /**
     * Get the localized title for the Manage API Keys page.
     *
     * @return string|Htmlable The localized page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_api_keys.title');
    }

    /**
     * Retrieve the localized label used for the page navigation.
     *
     * @return string The localized navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_api_keys.navigation_label');
    }

    /**
     * Resolve and return the application's ApiTokenService instance.
     *
     * @return ApiTokenService The service used to create and revoke personal access tokens.
     */
    protected function getTokenService(): ApiTokenService
    {
        return app(ApiTokenService::class);
    }

    /**
     * Retrieve the current tenant from Filament and abort with a 403 response if no tenant context exists.
     *
     * @return Tenant The current tenant instance.
     */
    protected function getTenant(): Tenant
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        return $tenant;
    }

    /**
     * Configure and return the table used to display and manage API tokens scoped to the current tenant.
     *
     * The table lists PersonalAccessToken records (eager-loading the `tokenable` relation) and provides columns
     * for name, owner email, abilities (badged and colorized), creation date, and last used date. It supports
     * searching, sorting, toggleable column visibility, defaults to sorting by newest creation date, includes a
     * revoke action that revokes a token for the tenant and shows success/error notifications, and defines a
     * localized empty state.
     *
     * @param  Table  $table  The Table instance to configure.
     * @return Table The configured Table instance for tenant-scoped PersonalAccessToken records.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                PersonalAccessToken::query()
                    ->where('tenant_id', $this->getTenant()->id)
                    ->with('tokenable')
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.pages.manage_api_keys.column_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tokenable.email')
                    ->label(__('filament.pages.manage_api_keys.column_owner'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('abilities')
                    ->label(__('filament.pages.manage_api_keys.column_abilities'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->color(fn ($state) => is_array($state) && in_array('admin-api', $state) ? 'danger' : 'success')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.pages.manage_api_keys.column_created'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label(__('filament.pages.manage_api_keys.column_last_used'))
                    ->dateTime(__('filament.date_time_format'))
                    ->placeholder(__('filament.pages.manage_api_keys.never'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                TableAction::make('revoke')
                    ->label(__('filament.pages.manage_api_keys.revoke'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament.pages.manage_api_keys.revoke_modal_title'))
                    ->modalDescription(__('filament.pages.manage_api_keys.revoke_modal_description'))
                    ->modalSubmitActionLabel(__('filament.pages.manage_api_keys.revoke_modal_submit'))
                    ->action(function (PersonalAccessToken $record) {
                        try {
                            $this->getTokenService()->revokeForTenant($record, $this->getTenant());

                            Notification::make()
                                ->title(__('filament.pages.manage_api_keys.token_revoked_title'))
                                ->body(__('filament.pages.manage_api_keys.token_revoked_body'))
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title(__('filament.pages.manage_api_keys.error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading(__('filament.pages.manage_api_keys.no_tokens'))
            ->emptyStateDescription(__('filament.pages.manage_api_keys.no_tokens_description'))
            ->emptyStateIcon('heroicon-o-key');
    }

    /**
     * Build the page header actions, including a "create token" action that opens a modal for creating tenant-scoped API tokens.
     *
     * The create action presents a form for token name and abilities; on submit it creates a token for the current tenant,
     * stores the new token's plaintext and display name for one-time display, and shows either a success notification or a
     * validation error notification.
     *
     * @return array The header action definitions.
     */
    protected function getHeaderActions(): array
    {
        $availableAbilities = $this->getAvailableAbilitiesTranslated();

        return [
            Action::make('create')
                ->label(__('filament.pages.manage_api_keys.create_token'))
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('name')
                        ->label(__('filament.pages.manage_api_keys.token_name'))
                        ->placeholder(__('filament.pages.manage_api_keys.token_name_placeholder'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('filament.pages.manage_api_keys.token_name_helper')),

                    CheckboxList::make('abilities')
                        ->label(__('filament.pages.manage_api_keys.abilities'))
                        ->options($availableAbilities)
                        ->required()
                        ->helperText(__('filament.pages.manage_api_keys.abilities_helper'))
                        ->columns(1),

                    Placeholder::make('warning')
                        ->label('')
                        ->content(__('filament.pages.manage_api_keys.token_warning'))
                        ->extraAttributes(['class' => 'text-warning-600 dark:text-warning-400']),
                ])
                ->modalHeading(__('filament.pages.manage_api_keys.create_modal_title'))
                ->modalSubmitActionLabel(__('filament.pages.manage_api_keys.create_modal_submit'))
                ->action(function (array $data) {
                    $user = Filament::auth()->user();
                    $tenant = $this->getTenant();

                    try {
                        $newToken = $this->getTokenService()->createForTenant(
                            $user,
                            $tenant,
                            $data['name'],
                            $data['abilities']
                        );

                        // Store for one-time display
                        $this->newTokenPlainText = $newToken->plainTextToken;
                        $this->newTokenName = $data['name'];

                        Notification::make()
                            ->title(__('filament.pages.manage_api_keys.token_created_title'))
                            ->body(__('filament.pages.manage_api_keys.token_created_body'))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title(__('filament.pages.manage_api_keys.validation_error'))
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Build a map of available API abilities to their translated labels.
     *
     * @return array<string,string> Associative array where keys are ability identifiers (e.g., `public-read`, `admin-api`) and values are their translated labels.
     */
    protected function getAvailableAbilitiesTranslated(): array
    {
        $abilities = [];
        foreach (ApiTokenService::ALLOWED_ABILITIES as $ability) {
            $key = str_replace('-', '_', $ability);
            $abilities[$ability] = __("filament.pages.manage_api_keys.ability_{$key}");
        }

        return $abilities;
    }

    /**
     * Reset the one-time display of a newly created API token.
     *
     * Clears the stored plain-text token and its display name so they are no longer shown to the user.
     */
    public function clearTokenDisplay(): void
    {
        $this->newTokenPlainText = null;
        $this->newTokenName = null;
    }
}
