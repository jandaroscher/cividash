<?php

namespace App\Filament\Resources\NavigationResource\Pages;

use App\Filament\Resources\NavigationResource;
use App\Models\Navigation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditNavigation extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = NavigationResource::class;

    /**
     * Initialize the edit page for the singleton Navigation resource, always using record id 1.
     *
     * @param int|string $record The incoming record identifier (ignored; the page always edits the singleton record with id 1).
     */
    public function mount(int | string $record): void
    {
        parent::mount(1);
    }

    /**
     * Provide header actions for the edit page, including a locale switcher.
     *
     * @return array<int, \Filament\Pages\Actions\Action> Array of header actions; contains a `LocaleSwitcher` action.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    /**
     * Resolve the Navigation record to edit for the singleton resource.
     *
     * This method ignores the provided key and always returns the singleton Navigation instance.
     *
     * @param int|string $key The requested record key (ignored).
     * @return Navigation The singleton Navigation instance used for editing.
     */
    protected function resolveRecord(int | string $key): Navigation
    {
        return Navigation::getOrCreateInstance();
    }

    /**
     * Retrieve the singleton navigation record for editing (the navigation instance with id = 1).
     *
     * @return Navigation The singleton Navigation instance used for the edit page.
     */
    public function getRecord(): Navigation
    {
        return Navigation::getOrCreateInstance();
    }

    /**
     * Extract locale-specific values from translatable nested fields before filling the form.
     *
     * Uses $this->activeLocale if set, otherwise the application's current locale. If the
     * provided $data contains a 'navigation_items' array, those items are converted so that
     * their translatable `label` and `url` fields contain values for the determined locale.
     *
     * @param array $data Form data array; may contain a 'navigation_items' key with translatable structures.
     * @return array The form data with 'navigation_items' converted to locale-specific fields when applicable.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        // Transform navigation_items to extract translatable label and url fields
        if (isset($data['navigation_items']) && is_array($data['navigation_items'])) {
            $data['navigation_items'] = $this->transformTranslatableRepeaterItems($data['navigation_items'], $locale);
        }

        return $data;
    }

    /**
     * Convert nested form fields into a translatable structure before persisting.
     *
     * Determines the active locale (activeLocale or app locale), preserves existing
     * translations for other locales, and converts `navigation_items` so that
     * `label` and `url` values are stored as per-locale arrays. Recursively handles
     * nested children and leaves other form data unchanged.
     *
     * @param array $data Form data to transform; may contain a `navigation_items` repeater.
     * @return array The transformed form data with `navigation_items` converted to translatable arrays.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();
        $record = $this->getRecord();

        // Get existing translations for other locales to preserve them
        $existingTranslations = [];
        foreach (['de', 'en'] as $existingLocale) {
            if ($existingLocale !== $locale) {
                $existingTranslations[$existingLocale] = $record->getTranslation('navigation_items', $existingLocale, false) ?? [];
            }
        }

        // Transform navigation_items to convert label and url back to translatable arrays
        if (isset($data['navigation_items']) && is_array($data['navigation_items'])) {
            $data['navigation_items'] = $this->convertRepeaterItemsToTranslatable(
                $data['navigation_items'],
                $locale,
                $existingTranslations
            );
        }

        return $data;
    }

    /**
     * Convert repeater items into a locale-keyed translatable structure suitable for saving, preserving translations for other locales and recursively processing children.
     *
     * Merges existing translations for the same item index from other locales, ensures the current locale key is present for `label` and `url`, and applies the same transformation to nested `children`.
     *
     * @param array $items Repeater items to convert; each item may contain `label`, `url`, and optional `children`.
     * @param string $locale The current locale to set or preserve values for.
     * @param array $existingTranslations Existing translations organized by locale → index → item (used to preserve other locales' values).
     * @return array The transformed items where `label` and `url` are arrays keyed by locale and `children` are recursively converted. 
     */
    protected function convertRepeaterItemsToTranslatable(array $items, string $locale, array $existingTranslations = []): array
    {
        return array_map(function ($item, $index) use ($locale, $existingTranslations) {
            // Merge with existing translations for other locales
            foreach ($existingTranslations as $existingLocale => $existingItems) {
                if (isset($existingItems[$index])) {
                    $existingItem = $existingItems[$index];
                    
                    // Preserve label translation for other locales
                    if (isset($existingItem['label']) && is_array($existingItem['label'])) {
                        if (!isset($item['label']) || !is_array($item['label'])) {
                            $item['label'] = $existingItem['label'];
                        } else {
                            $item['label'] = array_merge($existingItem['label'], $item['label']);
                        }
                    }
                    
                    // Preserve url translation for other locales
                    if (isset($existingItem['url']) && is_array($existingItem['url'])) {
                        if (!isset($item['url']) || !is_array($item['url'])) {
                            $item['url'] = $existingItem['url'];
                        } else {
                            $item['url'] = array_merge($existingItem['url'], $item['url']);
                        }
                    }
                }
            }

            // Convert label to translatable array if it's a string
            if (isset($item['label']) && !is_array($item['label'])) {
                $item['label'] = [$locale => (string)$item['label']];
            } elseif (isset($item['label']) && is_array($item['label'])) {
                // Ensure current locale is set
                $item['label'][$locale] = (string)($item['label'][$locale] ?? '');
            }

            // Convert url to translatable array if it's a string
            if (isset($item['url']) && !is_array($item['url'])) {
                $item['url'] = [$locale => (string)$item['url']];
            } elseif (isset($item['url']) && is_array($item['url'])) {
                // Ensure current locale is set
                $item['url'][$locale] = (string)($item['url'][$locale] ?? '');
            }

            // Recursively convert children
            if (isset($item['children']) && is_array($item['children'])) {
                $existingChildren = [];
                foreach ($existingTranslations as $existingLocale => $existingItems) {
                    if (isset($existingItems[$index]['children'])) {
                        $existingChildren[$existingLocale] = $existingItems[$index]['children'];
                    }
                }
                $item['children'] = $this->convertRepeaterItemsToTranslatable($item['children'], $locale, $existingChildren);
            }

            return $item;
        }, $items, array_keys($items));
    }

    /**
         * Convert nested repeater items so `label` and `url` contain values for the specified locale.
         *
         * For each item, if `label` or `url` are arrays of translations, the value for `$locale` is selected,
         * falling back to the 'de' value or an empty string when missing. Recursively processes `children`.
         *
         * @param array $items The repeater items to transform; may contain nested `children` and translatable `label`/`url` arrays.
         * @param string $locale The target locale key to extract (e.g. "en", "de").
         * @return array The transformed items with `label` and `url` set to the locale-specific string and `children` transformed likewise.
         */
    protected function transformTranslatableRepeaterItems(array $items, string $locale): array
    {
        return array_map(function ($item) use ($locale) {
            // Extract label for current locale
            if (isset($item['label']) && is_array($item['label'])) {
                $item['label'] = $item['label'][$locale] ?? $item['label']['de'] ?? '';
            }

            // Extract url for current locale
            if (isset($item['url']) && is_array($item['url'])) {
                $item['url'] = $item['url'][$locale] ?? $item['url']['de'] ?? '';
            }

            // Recursively transform children
            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->transformTranslatableRepeaterItems($item['children'], $locale);
            }

            return $item;
        }, $items);
    }

    /**
         * Switches the form's translatable data when the active locale changes.
         *
         * Preserves the current locale's translatable attributes into `otherLocaleData`,
         * restores any previously stored data for the newly active locale (transforming
         * nested `navigation_items` for the active locale), resets validation, and refills
         * the form with non‑translatable data combined with the locale‑specific data so
         * repeater fields display the correct translations.
         */
    public function updatedActiveLocale(): void
    {
        if (blank($this->oldActiveLocale)) {
            return;
        }

        $this->resetValidation();

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        $this->otherLocaleData[$this->oldActiveLocale] = Arr::only($this->data, $translatableAttributes);

        // Transform the data for the new locale before filling
        $newLocaleData = $this->otherLocaleData[$this->activeLocale] ?? [];
        if (isset($newLocaleData['navigation_items'])) {
            $newLocaleData['navigation_items'] = $this->transformTranslatableRepeaterItems(
                $newLocaleData['navigation_items'],
                $this->activeLocale
            );
        }

        // Fix repeater does not work with translations - see: https://github.com/filamentphp/filament/issues/8328#issuecomment-2060787867
        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$newLocaleData,
        ]);
        // Fix repeater does not work with translations - End

        unset($this->otherLocaleData[$this->activeLocale]);
    }
}