<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Filament\Resources\ThemeResource;
use App\Services\ThemeBundleImporter;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importThemeBundle')
                ->label(__('filament.resources.theme.import_action_label'))
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('bundle')
                        ->label(__('filament.resources.theme.import_bundle_field'))
                        ->disk('local')
                        ->directory('theme-imports')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize(25600)
                        ->required(),
                ])
                ->action(function (array $data, ThemeBundleImporter $importer): void {
                    $storedPath = $data['bundle'];

                    try {
                        $theme = $importer->import(Storage::disk('local')->path($storedPath));

                        Notification::make()
                            ->title(__('filament.resources.theme.import_success', ['name' => $theme->name]))
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title(__('filament.resources.theme.import_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        Storage::disk('local')->delete($storedPath);
                    }
                }),
        ];
    }
}
