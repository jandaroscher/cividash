<?php

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Resources\TileResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditTile extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = TileResource::class;

    protected array $categoryGroupState = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $locale = request()->query('activeLocale');

        if ($locale && in_array($locale, $this->getTranslatableLocales()) && $locale !== $this->activeLocale) {
            $this->setActiveLocale($locale);
        }
    }

    /****
     * Assemble the header action buttons for the edit page.
     *
     * Provides actions for switching locale, saving the record, opening the frontend view
     * for the current locale in a new tab, and deleting the record.
     *
     * @return array An array of action objects to display in the page header.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('view_frontend')
                ->label(__('filament.resources.tile.actions.view_frontend'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->url(function () {
                    $locale = $this->activeLocale ?? app()->getLocale();

                    return $this->record->getFrontendUrl(['locale' => $locale]);
                })
                ->openUrlInNewTab(),
            Action::make('save_header')
                ->label(__('filament.actions.save'))
                ->action('save'),
        ];
    }

    public function getFormMaxWidth(): ?string
    {
        return '7xl';
    }

    public function updatedActiveLocale(): void
    {
        if (blank($this->oldActiveLocale)) {
            return;
        }

        $this->resetValidation();

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        $this->otherLocaleData[$this->oldActiveLocale] = Arr::only($this->data, $translatableAttributes);

        // Fix repeater does not work with translations - see: https://github.com/filamentphp/filament/issues/8328#issuecomment-2060787867
        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$this->otherLocaleData[$this->activeLocale] ?? [],
        ]);
        // Fix repeater does not work with translations - End

        unset($this->otherLocaleData[$this->activeLocale]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Build the form's save action with a localized label, the 'save' action name, and the Mod+S keyboard shortcut.
     *
     * @return Action The configured save Action instance.
     */
    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament.actions.save'))
            ->action('save')
            ->keyBindings(['mod+s']);
    }

    /**
     * Extracts category group selection state from submitted form data and returns the form data with that state removed.
     *
     * Stores extracted category group state in $this->categoryGroupState for use after the record is saved.
     *
     * @param  array  $data  The incoming form data submitted for the record.
     * @return array The form data with category group state stripped out.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->categoryGroupState = TileResource::extractCategoryGroupState($data);

        return TileResource::stripCategoryGroupState($data);
    }

    /**
     * Synchronizes category group selections on the current record using the stored category group state.
     *
     * Persists the category-group relationships extracted from the form into the saved record.
     */
    protected function afterSave(): void
    {
        TileResource::syncCategoryGroupSelections($this->record, $this->categoryGroupState);
    }
}
