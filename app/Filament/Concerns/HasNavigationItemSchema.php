<?php

namespace App\Filament\Concerns;

use App\Models\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

trait HasNavigationItemSchema
{
    /**
     * Returns the schema for a navigation item (used for both header and footer navigation items).
     *
     * @return array Array of form components for a navigation item (type selector, page selector, label, URL).
     */
    protected function navigationItemSchema(): array
    {
        return [
            Select::make('type')
                ->label(__('filament.pages.manage_header.item_type'))
                ->options([
                    'page' => __('filament.pages.manage_header.type_page'),
                    'manual' => __('filament.pages.manage_header.type_manual'),
                ])
                ->default('page')
                ->required()
                ->reactive(),

            Select::make('page_id')
                ->label(__('filament.pages.manage_header.page'))
                ->options(function () {
                    return Page::query()
                        ->get()
                        ->mapWithKeys(function ($page) {
                            $title = $page->getTranslation('title', app()->getLocale(), false) 
                                ?: $page->getTranslation('title', 'de', false) 
                                ?: 'Untitled';
                            return [$page->id => $title];
                        })
                        ->toArray();
                })
                ->searchable()
                ->required(fn ($get) => $get('type') === 'page')
                ->visible(fn ($get) => $get('type') === 'page')
                ->reactive()
                ->afterStateUpdated(function ($state, $set, $get) {
                    if ($state && $get('type') === 'page') {
                        $page = Page::find($state);
                        if ($page) {
                            $locale = app()->getLocale();
                            $title = $page->getTranslation('title', $locale, false) 
                                ?: $page->getTranslation('title', 'de', false) 
                                ?: 'Untitled';
                            $url = $page->getUrl(['locale' => $locale]);
                            $set('label', $title);
                            $set('url', $url);
                        }
                    }
                }),

            TextInput::make('label')
                ->label(__('filament.pages.manage_header.label'))
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),

            TextInput::make('url')
                ->label(__('filament.pages.manage_header.url'))
                ->url()
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),
        ];
    }
}

