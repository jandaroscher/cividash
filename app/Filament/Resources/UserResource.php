<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'users';

    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.user.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.user.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.user.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()->with('tenants');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.user.sections.personal_data'))
                    ->schema([
                        Forms\Components\FileUpload::make('avatar_path')
                            ->label(__('filament.resources.user.fields.avatar'))
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('avatars')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('256')
                            ->imageResizeTargetHeight('256')
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('first_name')
                            ->label(__('filament.resources.user.fields.first_name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('last_name')
                            ->label(__('filament.resources.user.fields.last_name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label(__('filament.resources.user.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('phone')
                            ->label(__('filament.resources.user.fields.phone'))
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\Select::make('locale')
                            ->label(__('filament.resources.user.fields.locale'))
                            ->options([
                                'de' => 'Deutsch',
                                'en' => 'English',
                            ])
                            ->default('de')
                            ->native(false),

                        Forms\Components\TextInput::make('password')
                            ->label(__('filament.resources.user.fields.password'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->rule(Password::defaults())
                            ->minLength(12)
                            ->maxLength(64),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('filament.resources.user.fields.is_active'))
                            ->default(true)
                            ->inline(false)
                            ->disabled(fn (?User $record): bool => $record !== null && $record->id === Filament::auth()->id())
                            ->helperText(fn (?User $record): ?string => $record !== null && $record->id === Filament::auth()->id()
                                ? __('filament.resources.user.messages.cannot_deactivate_self')
                                : null),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.resources.user.sections.role_and_access'))
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label(__('filament.resources.user.fields.role'))
                            ->options([
                                'Admin' => __('filament.roles.Admin'),
                                'Redakteur' => __('filament.roles.Redakteur'),
                            ])
                            ->required()
                            ->default('Redakteur')
                            ->reactive()
                            ->dehydrated(false),

                        Forms\Components\Select::make('dashboard_assignments')
                            ->label(__('filament.resources.user.fields.dashboard_assignments'))
                            ->options(fn () => Tenant::pluck('name', 'id'))
                            ->multiple()
                            ->visible(fn (Get $get) => $get('role') === 'Redakteur')
                            ->required(fn (Get $get) => $get('role') === 'Redakteur')
                            ->dehydrated(false)
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label(__('filament.resources.user.fields.email'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('first_name')
                    ->label(__('filament.resources.user.fields.first_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label(__('filament.resources.user.fields.last_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('dashboard_access')
                    ->label(__('filament.resources.user.fields.dashboard_access'))
                    ->getStateUsing(function (User $record) {
                        if ($record->is_admin) {
                            return __('filament.roles.Admin').' ('.__('filament.resources.user.all_dashboards').')';
                        }

                        return $record->tenants->pluck('name')->join(', ');
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount('tenants')
                            ->orderBy('is_admin', $direction === 'asc' ? 'desc' : 'asc')
                            ->orderBy('tenants_count', $direction);
                    })
                    ->wrap(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('filament.resources.user.fields.is_active'))
                    ->sortable()
                    ->updateStateUsing(function ($record, $state) {
                        if (! $state && $record->id === Filament::auth()->id()) {
                            Notification::make()
                                ->title(__('filament.resources.user.messages.cannot_deactivate_self'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $state && $record->is_admin) {
                            if (! User::where('is_admin', true)->where('is_active', true)->where('id', '!=', $record->id)->exists()) {
                                Notification::make()
                                    ->title(__('filament.resources.user.messages.cannot_deactivate_last_admin'))
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        $record->is_active = $state;
                        $record->save();
                    }),
            ])
            ->defaultSort('last_name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label(__('filament.resources.user.fields.role'))
                    ->options([
                        'Admin' => __('filament.roles.Admin'),
                        'Redakteur' => __('filament.roles.Redakteur'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->where('is_admin', $data['value'] === 'Admin');
                    }),

                Tables\Filters\SelectFilter::make('dashboard_access')
                    ->label(__('filament.resources.user.fields.dashboard_access'))
                    ->options(fn () => Tenant::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->whereHas('tenants', fn (Builder $q) => $q->where('tenants.id', $data['value']));
                    })
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('filament.resources.user.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, User $record) {
                        if ($record->is_admin && ! User::where('is_admin', true)->where('is_active', true)->where('id', '!=', $record->id)->exists()) {
                            Notification::make()
                                ->title(__('filament.resources.user.messages.cannot_delete_last_admin'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $adminIdsToDelete = $records->where('is_admin', true)->where('is_active', true)->pluck('id');

                            if ($adminIdsToDelete->isEmpty()) {
                                return;
                            }

                            $remainingAdmins = User::where('is_admin', true)
                                ->where('is_active', true)
                                ->whereNotIn('id', $adminIdsToDelete)
                                ->exists();

                            if (! $remainingAdmins) {
                                Notification::make()
                                    ->title(__('filament.resources.user.messages.cannot_delete_last_admin'))
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
