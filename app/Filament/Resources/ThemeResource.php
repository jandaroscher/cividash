<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ThemeResource\Pages;
use App\Models\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 22;

    // Theme is a global entity shared across all dashboards, not tenant-scoped.
    protected static bool $isScopedToTenant = false;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.theme.section_theme'))
                    ->columns(1)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('filament.resources.theme.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, $record): void {
                                if ($record === null && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->label(__('filament.resources.theme.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('settings')
                            ->label(__('filament.resources.theme.settings'))
                            ->helperText(__('filament.resources.theme.settings_helper'))
                            ->rows(10)
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null)
                            ->rule('json')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('tenants'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.resources.theme.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('filament.resources.theme.slug'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label(__('filament.resources.theme.used_by_dashboards'))
                    ->badge()
                    ->formatStateUsing(fn (int $state) => trans_choice('filament.resources.theme.used_by_dashboards_count', $state, ['count' => $state]))
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.theme.created_at'))
                    ->dateTime(__('filament.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (Theme $record) => $record->tenants()->exists())
                    ->tooltip(fn (Theme $record) => $record->tenants()->exists()
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
            'index' => Pages\ListThemes::route('/'),
            'create' => Pages\CreateTheme::route('/create'),
            'edit' => Pages\EditTheme::route('/{record}/edit'),
        ];
    }
}
