<?php

namespace App\Filament\Pages;

use App\Settings\ContentSettings;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;

class ManageContent extends SettingsPage
{
    protected static string $settings = ContentSettings::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $title = 'Seiteneinstellungen';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Builder::make('hero_content')
                ->label(__('filament.pages.manage_content.hero_blocks'))
                ->blocks([
                    Builder\Block::make('heading')
                        ->label(__('filament.pages.manage_content.heading'))
                        ->schema([
                            TextInput::make('content')
                                ->label(__('filament.pages.manage_content.heading_text'))
                                ->required(),
                            TextInput::make('level')
                                ->label(__('filament.pages.manage_content.html_tag'))
                                ->required(),
                        ]),

                    Builder\Block::make('paragraph')
                        ->label(__('filament.pages.manage_content.paragraph'))
                        ->schema([
                            RichEditor::make('content')
                                ->label(__('filament.pages.manage_content.body_text'))
                                ->required(),
                        ]),

                    Builder\Block::make('image')
                        ->label(__('filament.pages.manage_content.image'))
                        ->schema([
                            FileUpload::make('url')
                                ->label(__('filament.pages.manage_content.image_file'))
                                ->image()
                                ->required(),
                            TextInput::make('alt')
                                ->label(__('filament.pages.manage_content.alt_text'))
                                ->required(),
                        ]),
                    Builder\Block::make('text_image')
                        ->label(__('filament.pages.manage_content.text_image'))
                        ->schema([
                            Grid::make()
                                ->schema([
                                    RichEditor::make('text')
                                        ->label(__('filament.pages.manage_content.text'))
                                        ->required(),
                                    FileUpload::make('image')
                                        ->label(__('filament.pages.manage_content.image'))
                                        ->image()
                                        ->directory('hero')
                                        ->required(),
                                ])
                                ->columns(2) // two equal-width columns
                                ->columnSpan('full'),
                        ])
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ]);
    }
}

