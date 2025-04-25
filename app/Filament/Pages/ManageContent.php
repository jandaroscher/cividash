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
    protected static ?string $title = 'Content Section';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Builder::make('hero_content')
                ->label('Hero Blocks')
                ->blocks([
                    Builder\Block::make('heading')
                        ->label('Heading')
                        ->schema([
                            TextInput::make('content')
                                ->label('Heading Text')
                                ->required(),
                            TextInput::make('level')
                                ->label('HTML Tag (h1–h6)')
                                ->required(),
                        ]),

                    Builder\Block::make('paragraph')
                        ->label('Paragraph')
                        ->schema([
                            RichEditor::make('content')
                                ->label('Body Text')
                                ->required(),
                        ]),

                    Builder\Block::make('image')
                        ->label('Image')
                        ->schema([
                            FileUpload::make('url')
                                ->label('Image File')
                                ->image()
                                ->required(),
                            TextInput::make('alt')
                                ->label('Alt Text')
                                ->required(),
                        ]),
                    Builder\Block::make('text_image')
                        ->label('Text + Image')
                        ->schema([
                            Grid::make()
                                ->schema([
                                    RichEditor::make('text')
                                        ->label('Text')
                                        ->required(),
                                    FileUpload::make('image')
                                        ->label('Image')
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

