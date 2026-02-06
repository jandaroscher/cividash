<?php

namespace App\Filament\Concerns;

use App\Models\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

trait HasNavigationItemSchema
{
    /**
     * Builds the form schema for a navigation item used by header and footer navigation.
     *
     * The schema includes a type selector, a page selector (which resolves localized page titles and can auto-fill label and URL),
     * a label input, and a URL input; visibility and requirement of fields depend on the chosen type.
     *
     * @return array Array of Filament form components for a navigation item: `type`, `page_id`, `label`, and `url`.
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
                    $locale = property_exists($this, 'activeLocale') ? $this->activeLocale : app()->getLocale();

                    return Page::query()
                        ->where('is_public', true)
                        ->get()
                        ->mapWithKeys(function ($page) use ($locale) {
                            $title = $page->getTranslation('title', $locale, false)
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
                            $locale = property_exists($this, 'activeLocale') ? $this->activeLocale : app()->getLocale();

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
