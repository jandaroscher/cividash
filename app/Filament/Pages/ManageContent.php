<?php

namespace App\Filament\Pages;

use App\Filament\Support\RichEditorConfig;
use App\Settings\ContentSettings;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;

class ManageContent extends SettingsPage
{
    protected static string $settings = ContentSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-window';

    public function getTitle(): string
    {
        return __('filament.pages.manage_content.title');
    }

    /**
     * Prevent the page from being added to the navigation menu.
     *
     * @return bool `false` to hide the page from navigation, `true` to register it.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hide from navigation menu
    }

    /**
     * Builds the settings page form schema with a `hero_content` builder containing
     * `heading`, `paragraph`, `image`, and `text_image` blocks for managing hero section content.
     *
     * @return \Filament\Forms\Form The configured form instance.
     */
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
                            RichEditorConfig::make('content')
                                ->label(__('filament.pages.manage_content.body_text'))
                                ->required(),
                        ]),

                    Builder\Block::make('image')
                        ->label(__('filament.pages.manage_content.image'))
                        ->schema([
                            FileUpload::make('url')
                                ->label(__('filament.pages.manage_content.image_file'))
                                ->image()
                                ->maxSize(5120) // 5 MB
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
                                    RichEditorConfig::make('text')
                                        ->label(__('filament.pages.manage_content.text'))
                                        ->required(),
                                    FileUpload::make('image')
                                        ->label(__('filament.pages.manage_content.image'))
                                        ->image()
                                        ->directory('hero')
                                        ->maxSize(5120) // 5 MB
                                        ->required(),
                                ])
                                ->columns(2) // two equal-width columns
                                ->columnSpan('full'),
                        ]),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
