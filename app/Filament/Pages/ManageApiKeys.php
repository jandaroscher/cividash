<?php

namespace App\Filament\Pages;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Services\ApiTokenService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ManageApiKeys extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $slug = 'api-keys';

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

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_api_keys.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_api_keys.navigation_label');
    }

    protected function getTokenService(): ApiTokenService
    {
        return app(ApiTokenService::class);
    }

    protected function getTenant(): Tenant
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        return $tenant;
    }

    public function table(Table $table): Table
    {
        $availableAbilities = $this->getAvailableAbilitiesTranslated();
        $badgeLabels = collect(ApiTokenService::ALLOWED_ABILITIES)
            ->mapWithKeys(fn (string $key) => [
                $key => __('filament.pages.manage_api_keys.ability_'.str_replace('-', '_', $key).'_short'),
            ])
            ->all();

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

                TextColumn::make('tokenable.first_name')
                    ->label(__('filament.pages.manage_api_keys.column_owner'))
                    ->formatStateUsing(fn ($record) => $record->tokenable?->full_name ?? '-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('abilities')
                    ->label(__('filament.pages.manage_api_keys.column_abilities'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $badgeLabels[$state] ?? $state)
                    ->color(fn ($state) => $state === 'admin-api' ? 'danger' : 'success')
                    ->toggleable(),

                ToggleColumn::make('is_active')
                    ->label(__('filament.pages.manage_api_keys.column_active'))
                    ->sortable()
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

                TextColumn::make('updated_at')
                    ->label(__('filament.pages.manage_api_keys.column_updated'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                TableAction::make('edit')
                    ->label(__('filament.pages.manage_api_keys.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        TextInput::make('name')
                            ->label(__('filament.pages.manage_api_keys.token_name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('abilities')
                            ->label(__('filament.pages.manage_api_keys.abilities'))
                            ->options($availableAbilities)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('filament.pages.manage_api_keys.column_active')),
                    ])
                    ->fillForm(fn (PersonalAccessToken $record) => [
                        'name' => $record->name,
                        'abilities' => $record->abilities[0] ?? 'public-read',
                        'is_active' => $record->is_active,
                    ])
                    ->modalHeading(__('filament.pages.manage_api_keys.edit_modal_title'))
                    ->modalSubmitActionLabel(__('filament.actions.save'))
                    ->action(function (PersonalAccessToken $record, array $data) {
                        try {
                            $data['abilities'] = [$data['abilities']];
                            $this->getTokenService()->updateForTenant($record, $this->getTenant(), $data);

                            Notification::make()
                                ->title(__('filament.pages.manage_api_keys.token_updated_title'))
                                ->body(__('filament.pages.manage_api_keys.token_updated_body'))
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title(__('filament.pages.manage_api_keys.error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title(__('filament.pages.manage_api_keys.validation_error'))
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    })
                    ->extraModalFooterActions([
                        TableAction::make('deleteFromEdit')
                            ->label(__('filament.pages.manage_api_keys.delete'))
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading(__('filament.pages.manage_api_keys.delete_modal_title'))
                            ->modalDescription(__('filament.pages.manage_api_keys.delete_modal_description'))
                            ->action(function (PersonalAccessToken $record) {
                                try {
                                    $this->getTokenService()->revokeForTenant($record, $this->getTenant());

                                    Notification::make()
                                        ->title(__('filament.pages.manage_api_keys.token_deleted_title'))
                                        ->body(__('filament.pages.manage_api_keys.token_deleted_body'))
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
                    ]),

                TableAction::make('delete')
                    ->label(__('filament.pages.manage_api_keys.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament.pages.manage_api_keys.delete_modal_title'))
                    ->modalDescription(__('filament.pages.manage_api_keys.delete_modal_description'))
                    ->modalSubmitActionLabel(__('filament.pages.manage_api_keys.delete_modal_submit'))
                    ->action(function (PersonalAccessToken $record) {
                        try {
                            $this->getTokenService()->revokeForTenant($record, $this->getTenant());

                            Notification::make()
                                ->title(__('filament.pages.manage_api_keys.token_deleted_title'))
                                ->body(__('filament.pages.manage_api_keys.token_deleted_body'))
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
            ->filters([
                SelectFilter::make('abilities')
                    ->label(__('filament.pages.manage_api_keys.filter_abilities'))
                    ->options($availableAbilities)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn (Builder $q, string $v) => $q->whereJsonContains('abilities', $v)
                    )),
                TernaryFilter::make('is_active')
                    ->label(__('filament.pages.manage_api_keys.column_active')),
            ])
            ->emptyStateHeading(__('filament.pages.manage_api_keys.no_tokens'))
            ->emptyStateDescription(__('filament.pages.manage_api_keys.no_tokens_description'))
            ->emptyStateIcon('heroicon-o-key');
    }

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

                    Select::make('abilities')
                        ->label(__('filament.pages.manage_api_keys.abilities'))
                        ->options($availableAbilities)
                        ->default('public-read')
                        ->selectablePlaceholder(false)
                        ->required()
                        ->helperText(__('filament.pages.manage_api_keys.abilities_helper')),

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
                            [$data['abilities']]
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
     */
    protected function getAvailableAbilitiesTranslated(): array
    {
        return collect(ApiTokenService::ALLOWED_ABILITIES)
            ->mapWithKeys(fn (string $key) => [
                $key => __('filament.pages.manage_api_keys.ability_'.str_replace('-', '_', $key)),
            ])
            ->all();
    }

    /**
     * Reset the one-time display of a newly created API token.
     */
    public function clearTokenDisplay(): void
    {
        $this->newTokenPlainText = null;
        $this->newTokenName = null;
    }
}
