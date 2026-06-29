<?php

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Resources\TileResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
            Action::make('publishToCore')
                ->label(__('filament.resources.tile.actions.publish'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->visible(fn (): bool => TileResource::canPublishToCore())
                // Server-side gate: visible() is render-only, so authorize()
                // also blocks direct Livewire invocation by non-admins.
                ->authorize(fn (): bool => TileResource::canUserPublishToCore())
                ->requiresConfirmation()
                ->modalHeading(__('filament.resources.tile.actions.publish_confirm_heading'))
                ->modalDescription(__('filament.resources.tile.actions.publish_confirm_description'))
                ->modalSubmitActionLabel(__('filament.resources.tile.actions.publish_confirm_submit'))
                ->action(fn () => TileResource::handlePublishToCore($this->record)),
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

    /**
     * Override to normalize FileUpload state in other-locale background_blocks
     * before validation. The translatable plugin sets $this->data directly with
     * raw DB values (strings for file paths), but BaseFileUpload's validation
     * closure requires array values, causing a TypeError.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Normalize file upload strings in otherLocaleData before parent runs validation
        foreach ($this->otherLocaleData as $locale => &$localeData) {
            if (isset($localeData['background_blocks']) && is_array($localeData['background_blocks'])) {
                $localeData['background_blocks'] = $this->normalizeFileUploadsInBlocks($localeData['background_blocks']);
            }
        }
        unset($localeData);

        return parent::handleRecordUpdate($record, $data);
    }

    /**
     * Walk through Builder blocks and wrap any string file-upload values in UUID-keyed arrays.
     *
     * File upload fields: image (IntroText, TextImage, Slider items), image_secondary (IntroText).
     */
    protected function normalizeFileUploadsInBlocks(array $blocks): array
    {
        $fileUploadKeys = ['image', 'image_secondary'];

        foreach ($blocks as &$block) {
            if (! isset($block['data']) || ! is_array($block['data'])) {
                continue;
            }

            // Normalize top-level file upload fields
            foreach ($fileUploadKeys as $key) {
                if (isset($block['data'][$key]) && is_string($block['data'][$key]) && filled($block['data'][$key])) {
                    $block['data'][$key] = [(string) Str::uuid() => $block['data'][$key]];
                }
            }

            // Normalize file uploads inside repeater items (e.g., SliderBlock's items)
            if (isset($block['data']['items']) && is_array($block['data']['items'])) {
                foreach ($block['data']['items'] as &$item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    foreach ($fileUploadKeys as $key) {
                        if (isset($item[$key]) && is_string($item[$key]) && filled($item[$key])) {
                            $item[$key] = [(string) Str::uuid() => $item[$key]];
                        }
                    }
                }
                unset($item);
            }
        }
        unset($block);

        return $blocks;
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
