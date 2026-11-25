<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources;

use App\Filament\Resources\ThemeResource\Pages\CreateTheme;
use App\Filament\Resources\ThemeResource\Pages\EditTheme;
use App\Filament\Resources\ThemeResource\Pages\ListThemes;
use App\Models\Theme;
use App\Settings\BrandingSettings;
use App\Settings\ContentSettings;
use App\Settings\DashboardSettings;
use App\Settings\GeneralSettings;
use App\Settings\IntegrationSettings;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 22;

    // Theme is a global entity shared across all dashboards, not tenant-scoped.
    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.theme.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.theme.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.theme.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.resources.theme.section_theme'))
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.resources.theme.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, $record): void {
                                if ($record === null && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('filament.resources.theme.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('settings')
                            ->label(__('filament.resources.theme.settings'))
                            ->helperText(__('filament.resources.theme.settings_helper'))
                            ->rows(10)
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null)
                            ->rule('json')
                            ->rule(function () {
                                return function (string $attribute, $value, Closure $fail) {
                                    if (! filled($value)) {
                                        return;
                                    }

                                    $decoded = json_decode($value, true);

                                    if (! is_array($decoded) || array_is_list($decoded)) {
                                        $fail(__('filament.resources.theme.settings_invalid_structure'));

                                        return;
                                    }

                                    $knownGroups = [
                                        GeneralSettings::group(),
                                        ContentSettings::group(),
                                        DashboardSettings::group(),
                                        BrandingSettings::group(),
                                        IntegrationSettings::group(),
                                    ];

                                    foreach ($decoded as $group => $groupSettings) {
                                        if (! in_array($group, $knownGroups, true)) {
                                            $fail(__('filament.resources.theme.settings_unknown_group', ['group' => $group]));

                                            return;
                                        }

                                        if (! is_array($groupSettings) || array_is_list($groupSettings)) {
                                            $fail(__('filament.resources.theme.settings_invalid_structure'));

                                            return;
                                        }
                                    }
                                };
                            })
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('tenants'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.resources.theme.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('filament.resources.theme.slug'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenants_count')
                    ->label(__('filament.resources.theme.used_by_dashboards'))
                    ->badge()
                    ->formatStateUsing(fn (int $state) => trans_choice('filament.resources.theme.used_by_dashboards_count', $state, ['count' => $state]))
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament.resources.theme.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Theme $record) => $record->tenants_count > 0)
                    ->tooltip(fn (Theme $record) => $record->tenants_count > 0
                        ? __('filament.resources.theme.delete_blocked_tooltip')
                        : null),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThemes::route('/'),
            'create' => CreateTheme::route('/create'),
            'edit' => EditTheme::route('/{record}/edit'),
        ];
    }
}
