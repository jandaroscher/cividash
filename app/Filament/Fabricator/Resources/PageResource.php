<?php

namespace App\Filament\Fabricator\Resources;

use App\Filament\Fabricator\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;
use Z3d0X\FilamentFabricator\Enums\ResourceSchemaSlot;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;

class PageResource extends FabricatorPageResource
{
    use Translatable;

    protected static ?string $navigationGroup = 'Inhalte';
    protected static ?int $navigationSort = 1;

    /**
     * Provides the label displayed for this resource in the Filament navigation.
     *
     * @return string The navigation label "Seiten".
     */
    public static function getNavigationLabel(): string
    {
        return 'Seiten';
    }

    /**
     * Customize the parent form to replace the sidebar's `page_url` placeholder with a locale-aware placeholder.
     *
     * Replaces any `Placeholder` named `page_url` inside sidebar sections with a `Placeholder` that:
     * - is labeled with the translated URL label,
     * - is visible only when Fabricator routing is enabled and a record exists,
     * - displays `'-'` when no record is present,
     * - otherwise returns the record URL for the active locale (from the Livewire component's `activeLocale` property or `app()->getLocale()`).
     *
     * @param \Filament\Forms\Form $form The base form to modify.
     * @return \Filament\Forms\Form The modified form instance.
     */
    public static function form(Form $form): Form
    {
        // Get the base form structure from parent
        $form = parent::form($form);
        
        // We need to customize the sidebar schema to make the URL field locale-aware
        // The parent uses a Section with page_url Placeholder, we need to override it
        
        // Get all components
        $components = $form->getComponents();
        
        // Find the right sidebar column and replace the page_url Placeholder
        foreach ($components as $columnKey => $column) {
            if (method_exists($column, 'getChildComponents')) {
                $childComponents = $column->getChildComponents();
                
                foreach ($childComponents as $sectionKey => $section) {
                    if ($section instanceof Section && method_exists($section, 'getChildComponents')) {
                        $sectionChildren = $section->getChildComponents();
                        
                        foreach ($sectionChildren as $placeholderKey => $placeholder) {
                            if ($placeholder instanceof Placeholder && $placeholder->getName() === 'page_url') {
                                // Replace with our custom locale-aware version
                                $sectionChildren[$placeholderKey] = Placeholder::make('page_url')
                                    ->label(__('filament-fabricator::page-resource.labels.url'))
                                    ->visible(fn (?Page $record) => config('filament-fabricator.routing.enabled') && filled($record))
                                    ->content(function (?Page $record, $livewire) {
                                        if (!$record) {
                                            return '-';
                                        }
                                        
                                        // Get the active locale from the Livewire component
                                        $activeLocale = property_exists($livewire, 'activeLocale') 
                                            ? $livewire->activeLocale 
                                            : app()->getLocale();
                                        
                                        // Generate URL with the correct locale
                                        return $record->getUrl(['locale' => $activeLocale]);
                                    });
                                
                                // Update the section with modified children
                                $section->schema($sectionChildren);
                            }
                        }
                    }
                }
            }
        }
        
        return $form;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
