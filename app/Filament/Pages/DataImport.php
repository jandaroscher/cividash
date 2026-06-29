<?php

namespace App\Filament\Pages;

use App\Models\ImportRun;
use App\Models\Tenant;
use App\Services\Import\ImportService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Filament page for bulk data imports.
 *
 * Workflow:
 *   1. User uploads a .json bundle.
 *   2. "Dry-Run starten" previews the diff (rollback).
 *   3. "Import bestätigen" commits the same bundle.
 * Recent runs are listed in the table below.
 */
class DataImport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $slug = 'data-import';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.data-import';

    public ?array $data = [];

    /** Serialised ImportResult of the most recent dry-run (null if none yet). */
    public ?array $lastResult = null;

    public ?string $lastResultMode = null;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.system');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.data_import.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.data_import.navigation_label');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadSchema')
                ->label(__('filament.pages.data_import.actions.download_schema'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(url('/api/import/schemas/bundle'), shouldOpenInNewTab: true),
            Action::make('downloadExample')
                ->label(__('filament.pages.data_import.actions.download_example'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(url('/api/import/examples/valid-full'), shouldOpenInNewTab: true),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.pages.data_import.form.section_title'))
                    ->description(__('filament.pages.data_import.form.section_description'))
                    ->icon('heroicon-o-document-arrow-up')
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('filament.pages.data_import.form.file_label'))
                            ->helperText(__('filament.pages.data_import.form.file_hint'))
                            ->acceptedFileTypes(['application/json', 'text/plain'])
                            ->maxSize(20480) // 20 MB
                            ->required()
                            ->disk('local')
                            ->directory('imports/tmp')
                            ->preserveFilenames(),
                    ]),
            ])
            ->statePath('data');
    }

    public function dryRun(): void
    {
        $this->runImport('dry_run');
    }

    public function commit(): void
    {
        $this->runImport('commit');
    }

    private function runImport(string $mode): void
    {
        $data = $this->form->getState();
        $relativePath = is_array($data['file'] ?? null) ? reset($data['file']) : ($data['file'] ?? null);

        if (! $relativePath) {
            Notification::make()
                ->title(__('filament.pages.data_import.notifications.missing_file'))
                ->danger()
                ->send();

            return;
        }

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($relativePath);

        if (! is_file($absolutePath)) {
            Notification::make()
                ->title(__('filament.pages.data_import.notifications.file_not_found'))
                ->danger()
                ->send();

            return;
        }

        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        $originalName = basename($relativePath);
        $upload = new UploadedFile(
            path: $absolutePath,
            originalName: $originalName,
            mimeType: 'application/json',
            error: null,
            test: true,
        );

        $result = app(ImportService::class)->import(
            file: $upload,
            tenant: $tenant,
            userId: $user?->id,
            mode: $mode,
        );

        $this->lastResult = $result->toArray();
        $this->lastResultMode = $mode;

        if ($result->failed()) {
            Notification::make()
                ->title(__('filament.pages.data_import.notifications.failed', ['count' => count($result->errors)]))
                ->danger()
                ->send();

            return;
        }

        if ($mode === 'dry_run') {
            Notification::make()
                ->title(__('filament.pages.data_import.notifications.dry_run_ok'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('filament.pages.data_import.notifications.commit_ok'))
                ->success()
                ->send();

            // Clear form and preview after successful commit.
            $this->form->fill();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ImportRun::query()->latest('id'))
            ->modifyQueryUsing(fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament.pages.data_import.table.created_at'))
                    ->dateTime('d.m.Y H:i:s'),
                TextColumn::make('filename')
                    ->label(__('filament.pages.data_import.table.filename'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('mode')
                    ->label(__('filament.pages.data_import.table.mode'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dry_run' => 'warning',
                        'commit' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label(__('filament.pages.data_import.table.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('error_count')
                    ->label(__('filament.pages.data_import.table.errors'))
                    ->numeric(),
                TextColumn::make('duration_ms')
                    ->label(__('filament.pages.data_import.table.duration'))
                    ->suffix(' ms'),
                TextColumn::make('user.email')
                    ->label(__('filament.pages.data_import.table.user')),
            ])
            ->defaultPaginationPageOption(10);
    }
}
