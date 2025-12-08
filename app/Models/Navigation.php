<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Navigation extends Model
{
    use HasTranslations;

    /**
     * List of translatable fields.
     */
    public array $translatable = [
        'navigation_items',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'navigation_items',
        'show_language_switcher',
        'dropdown_enabled',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'navigation_items' => 'array',
        'show_language_switcher' => 'boolean',
        'dropdown_enabled' => 'boolean',
    ];

    /**
     * Retrieve the Navigation model with id = 1.
     *
     * @return Navigation The Navigation model with id = 1.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If no record with id = 1 exists.
     */
    public static function getInstance(): Navigation
    {
        return static::findOrFail(1);
    }

    /**
     * Retrieve the singleton Navigation model with id = 1, creating it with default attributes if it does not exist.
     *
     * @return Navigation The Navigation model instance with id = 1.
     */
    public static function getOrCreateInstance(): Navigation
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'navigation_items' => [],
                'show_language_switcher' => true,
                'dropdown_enabled' => false,
            ]
        );
    }

    /**
     * Retrieve navigation items translated for the given locale or the application's current locale.
     *
     * @param string|null $locale Locale to use for translations; when null the application's current locale is used.
     * @return array Array of navigation items with `label` and `url` fields translated for the resolved locale. If a translation is missing the method falls back to the 'de' locale or an empty string. Child items in a `children` array are translated recursively.
     */
    public function getTranslatedNavigationItems(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try to get translation for requested locale first (without fallback)
        $items = $this->getTranslation('navigation_items', $locale, false);
        
        // If we got an array, check if it's the translatable structure (has locale keys like 'de', 'en')
        // or if it's already the items array (has numeric keys)
        if (is_array($items)) {
            // Check if this is a translatable structure (has locale keys)
            if (isset($items['de']) || isset($items['en']) || isset($items[$locale])) {
                // This is the translatable structure
                // Check if requested locale has a non-empty value, otherwise fallback to 'de'
                if (isset($items[$locale]) && !empty($items[$locale])) {
                    $items = $items[$locale];
                } else {
                    $items = $items['de'] ?? [];
                }
            }
            // Otherwise, items is already the array of navigation items for the requested locale
        }
        
        // If null or empty array and locale is not 'de', try to get 'de' translation
        if (($items === null || (is_array($items) && empty($items))) && $locale !== 'de') {
            $itemsDe = $this->getTranslation('navigation_items', 'de', false);
            if (is_array($itemsDe)) {
                // Check if this is a translatable structure
                if (isset($itemsDe['de']) || isset($itemsDe['en'])) {
                    $items = $itemsDe['de'] ?? [];
                } else {
                    $items = $itemsDe;
                }
            } else {
                $items = $itemsDe ?? [];
            }
        }

        $items = $items ?? [];

        return array_map(function ($item) use ($locale) {
            $translatedItem = $item;

            // Translate label if it's translatable
            if (isset($item['label']) && is_array($item['label'])) {
                $translatedItem['label'] = $item['label'][$locale] ?? $item['label']['de'] ?? '';
            }

            // Translate URL if it's translatable
            if (isset($item['url']) && is_array($item['url'])) {
                $translatedItem['url'] = $item['url'][$locale] ?? $item['url']['de'] ?? '';
            }

            // Recursively translate children
            if (isset($item['children']) && is_array($item['children'])) {
                $translatedItem['children'] = array_map(function ($child) use ($locale) {
                    $translatedChild = $child;
                    if (isset($child['label']) && is_array($child['label'])) {
                        $translatedChild['label'] = $child['label'][$locale] ?? $child['label']['de'] ?? '';
                    }
                    if (isset($child['url']) && is_array($child['url'])) {
                        $translatedChild['url'] = $child['url'][$locale] ?? $child['url']['de'] ?? '';
                    }
                    return $translatedChild;
                }, $item['children']);
            }

            return $translatedItem;
        }, $items);
    }
}