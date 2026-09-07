<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Support\RichEditorConfig;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class DownloadBlock extends PageBlock
{
    use HasBlockActiveToggleAction;

    public static function getBlockSchema(): Block
    {
        return Block::make('download')
            ->label(__('filament.blocks.download.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->schema([
                TextInput::make('heading')
                    ->label(__('filament.blocks.download.heading'))
                    ->maxLength(255),
                RichEditorConfig::make('text')
                    ->label(__('filament.blocks.download.text')),
                Repeater::make('items')
                    ->label(__('filament.blocks.download.items'))
                    ->addActionLabel(__('filament.actions.add_to_download_items'))
                    ->extraItemActions([
                        static::getBlockActiveToggleAction(),
                    ])
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament.blocks.download.title'))
                            ->maxLength(255)
                            ->helperText(__('filament.blocks.download.title_helper')),
                        FileUpload::make('file')
                            ->label(__('filament.blocks.download.file'))
                            ->disk('public')
                            ->directory('downloads')
                            ->preserveFilenames()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/zip',
                            ])
                            ->maxSize(25600) // 25 MB
                            ->required(),
                        Hidden::make('is_active')
                            ->default(true)
                            ->afterStateHydrated(function (Hidden $component, $state): void {
                                if ($state === null) {
                                    $component->state(true);
                                }
                            }),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->defaultItems(1)
                    ->collapsible()
                    ->collapsed(),
                Hidden::make('is_active')
                    ->default(true)
                    ->afterStateHydrated(function (Hidden $component, $state): void {
                        if ($state === null) {
                            $component->state(true);
                        }
                    }),
            ]);
    }
}
