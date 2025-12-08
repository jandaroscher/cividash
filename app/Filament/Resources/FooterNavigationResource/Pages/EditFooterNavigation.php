<?php

namespace App\Filament\Resources\FooterNavigationResource\Pages;

use App\Filament\Resources\FooterNavigationResource;
use App\Models\FooterNavigation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditFooterNavigation extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = FooterNavigationResource::class;

    /**
     * Mounts the page using the singleton record with id 1.
     *
     * @param int|string $record Incoming record identifier; ignored — the page always mounts record id 1 to enforce singleton behavior.
     */
    public function mount(int | string $record): void
    {
        parent::mount(1);
    }

    /**
     * Provide header actions for the page, enabling locale switching.
     *
     * @return array An array of Filament header action instances; includes a `LocaleSwitcher` action.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    /**
     * Resolve the record for editing (always id=1 for singleton).
     */
    protected function resolveRecord(int | string $key): FooterNavigation
    {
        return FooterNavigation::getOrCreateInstance();
    }

    /**
     * Retrieves the singleton FooterNavigation instance used for editing (always the record with id 1).
     *
     * @return FooterNavigation The singleton FooterNavigation instance used by the edit page.
     */
    public function getRecord(): FooterNavigation
    {
        return FooterNavigation::getOrCreateInstance();
    }

    /**
     * Prepare form data for display by extracting values for the active locale from translatable fields.
     *
     * Transforms the following keys when present:
     * - `footer_navigation_items`: extracts `label` and `url` for the active locale from nested translatable structures.
     * - `social_links`: replaces each `title` translatable array with the title string for the active locale (falls back to `de`).
     * - `copyright_text`: replaces the translatable array with the string for the active locale (falls back to `de`).
     *
     * The active locale is taken from `$this->activeLocale` if set, otherwise from `app()->getLocale()`.
     *
     * @param array $data Raw record data possibly containing translatable arrays.
     * @return array Form-ready data with locale-specific scalar values for the transformed fields.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        // Transform footer_navigation_items to extract translatable label and url fields
        if (isset($data['footer_navigation_items']) && is_array($data['footer_navigation_items'])) {
            $data['footer_navigation_items'] = $this->transformTranslatableRepeaterItems($data['footer_navigation_items'], $locale);
        }

        // Transform social_links to extract translatable title field
        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $data['social_links'] = array_map(function ($link) use ($locale) {
                if (isset($link['title']) && is_array($link['title'])) {
                    $link['title'] = $link['title'][$locale] ?? $link['title']['de'] ?? '';
                }
                return $link;
            }, $data['social_links']);
        }

        // Transform copyright_text
        if (isset($data['copyright_text']) && is_array($data['copyright_text'])) {
            $data['copyright_text'] = $data['copyright_text'][$locale] ?? $data['copyright_text']['de'] ?? '';
        }

        return $data;
    }

    /**
         * Convert current-locale form fields into translatable structures and preserve existing translations for other locales.
         *
         * Transforms nested form fields so they can be saved as translatable data:
         * - Converts `footer_navigation_items` repeater items' `label` and `url` into per-locale arrays, preserving other locales' entries.
         * - Converts each `social_links` entry's `title` into a per-locale array, merging with existing translations for the same index.
         * - Converts `copyright_text` into a per-locale value, merging with any existing translations.
         *
         * @param array $data Form data to transform.
         * @return array The transformed form data ready for persistence.
         */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();
        $record = $this->getRecord();

        // Get existing translations for other locales to preserve them
        $existingTranslations = [];
        foreach (['de', 'en'] as $existingLocale) {
            if ($existingLocale !== $locale) {
                $existingTranslations[$existingLocale] = [
                    'footer_navigation_items' => $record->getTranslation('footer_navigation_items', $existingLocale, false) ?? [],
                    'social_links' => $record->getTranslation('social_links', $existingLocale, false) ?? [],
                    'copyright_text' => $record->getTranslation('copyright_text', $existingLocale, false),
                ];
            }
        }

        // Transform footer_navigation_items to convert label and url back to translatable arrays
        if (isset($data['footer_navigation_items']) && is_array($data['footer_navigation_items'])) {
            $existingItems = [];
            foreach ($existingTranslations as $existingLocale => $existingData) {
                $existingItems[$existingLocale] = $existingData['footer_navigation_items'] ?? [];
            }
            $data['footer_navigation_items'] = $this->convertRepeaterItemsToTranslatable(
                $data['footer_navigation_items'],
                $locale,
                $existingItems
            );
        }

        // Transform social_links to convert title back to translatable array
        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $existingSocialLinks = [];
            foreach ($existingTranslations as $existingLocale => $existingData) {
                $existingSocialLinks[$existingLocale] = $existingData['social_links'] ?? [];
            }
            $data['social_links'] = array_map(function ($link, $index) use ($locale, $existingSocialLinks) {
                // Merge with existing translations for other locales
                foreach ($existingSocialLinks as $existingLocale => $existingLinks) {
                    if (isset($existingLinks[$index]['title']) && is_array($existingLinks[$index]['title'])) {
                        if (!isset($link['title']) || !is_array($link['title'])) {
                            $link['title'] = $existingLinks[$index]['title'];
                        } else {
                            $link['title'] = array_merge($existingLinks[$index]['title'], $link['title']);
                        }
                    }
                }
                
                if (isset($link['title']) && !is_array($link['title'])) {
                    $link['title'] = [$locale => (string)$link['title']];
                } elseif (isset($link['title']) && is_array($link['title'])) {
                    $link['title'][$locale] = (string)($link['title'][$locale] ?? '');
                }
                return $link;
            }, $data['social_links'], array_keys($data['social_links']));
        }

        // Transform copyright_text back to translatable array
        $existingCopyrightText = null;
        foreach ($existingTranslations as $existingLocale => $existingData) {
            if (isset($existingData['copyright_text'])) {
                $existingCopyrightText = is_array($existingData['copyright_text']) 
                    ? $existingData['copyright_text'] 
                    : [$existingLocale => $existingData['copyright_text']];
                break;
            }
        }
        
        if (isset($data['copyright_text'])) {
            if (!is_array($data['copyright_text'])) {
                $data['copyright_text'] = array_merge($existingCopyrightText ?? [], [$locale => (string)$data['copyright_text']]);
            } elseif (is_array($data['copyright_text'])) {
                $data['copyright_text'] = array_merge($existingCopyrightText ?? [], $data['copyright_text']);
                $data['copyright_text'][$locale] = (string)($data['copyright_text'][$locale] ?? '');
            }
        }

        return $data;
    }

    /**
         * Convert a repeater's items into translatable structures for the given locale while preserving translations for other locales.
         *
         * Converts string `label` and `url` values into arrays keyed by the provided `$locale`, ensures the current locale entry exists when those fields are already arrays, and recursively processes nested `children` items. Existing translations for other locales are merged from `$existingTranslations` to preserve their values.
         *
         * @param array $items The repeater items to convert (each item may contain `label`, `url`, and `children`).
         * @param string $locale The locale key to set or ensure on each translatable field (e.g., "en", "de").
         * @param array $existingTranslations Optional existing translations indexed by locale to merge and preserve other-locale values.
         * @return array The items transformed into translatable format with merged existing translations.
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
     * Extract current-locale values from translatable repeater items for form display.
     *
     * Replaces `label` and `url` entries that are arrays of translations with the value for the given locale,
     * falling back to the 'de' locale and then to an empty string if no value exists. Recursively applies the
     * same transformation to nested `children` items.
     *
     * @param array $items Repeater items, each may contain `label`, `url`, and nested `children`.
     * @param string $locale Locale code to extract (e.g., 'en', 'de').
     * @return array The repeater items with `label` and `url` converted to locale-specific strings and `children` transformed recursively.
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
     * Handle locale switching for translatable fields.
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
        
        if (isset($newLocaleData['footer_navigation_items'])) {
            $newLocaleData['footer_navigation_items'] = $this->transformTranslatableRepeaterItems(
                $newLocaleData['footer_navigation_items'],
                $this->activeLocale
            );
        }

        if (isset($newLocaleData['social_links'])) {
            $newLocaleData['social_links'] = array_map(function ($link) {
                if (isset($link['title']) && is_array($link['title'])) {
                    $link['title'] = $link['title'][$this->activeLocale] ?? $link['title']['de'] ?? '';
                }
                return $link;
            }, $newLocaleData['social_links']);
        }

        if (isset($newLocaleData['copyright_text']) && is_array($newLocaleData['copyright_text'])) {
            $newLocaleData['copyright_text'] = $newLocaleData['copyright_text'][$this->activeLocale] ?? $newLocaleData['copyright_text']['de'] ?? '';
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