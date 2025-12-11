<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class FooterNavigation extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    /**
     * List of translatable fields.
     */
    public array $translatable = [
        'footer_navigation_items',
        'social_links',
        'copyright_text',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'footer_navigation_items',
        'social_links',
        'layout_type',
        'columns',
        'social_links_enabled',
        'copyright_text',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'footer_navigation_items' => 'array',
        'social_links' => 'array',
        'columns' => 'integer',
        'social_links_enabled' => 'boolean',
        'copyright_text' => 'array',
    ];

    /**
     * Retrieve the FooterNavigation record with id 1.
     *
     * @return FooterNavigation The FooterNavigation model with id 1.
     */
    public static function getInstance(): FooterNavigation
    {
        return static::findOrFail(1);
    }

    /**
     * Get or create the singleton instance (id=1).
     * 
     * @return FooterNavigation
     */
    public static function getOrCreateInstance(): FooterNavigation
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'footer_navigation_items' => [],
                'social_links' => [],
                'layout_type' => 'single-row',
                'columns' => 3,
                'social_links_enabled' => true,
                'copyright_text' => null,
            ]
        );
    }

    /**
         * Return footer navigation items with labels and URLs translated for the resolved locale.
         *
         * @param string|null $locale Locale to use for translations; when null the application locale is used.
         * @return array Footer navigation items where `label` and `url` have been translated for the resolved locale.
         */
    public function getTranslatedFooterNavigationItems(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try to get translation for requested locale first (without fallback)
        $items = $this->getTranslation('footer_navigation_items', $locale, false);
        
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
            // Otherwise, items is already the array of footer navigation items for the requested locale
        }
        
        // If null or empty array and locale is not 'de', try to get 'de' translation
        if (($items === null || (is_array($items) && empty($items))) && $locale !== 'de') {
            $itemsDe = $this->getTranslation('footer_navigation_items', 'de', false);
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

            return $translatedItem;
        }, $items);
    }

    /**
     * Provide social links with titles translated for a given locale.
     *
     * @param string|null $locale The locale to use for translations; when null the application locale is used.
     * @return array Social link arrays with the `title` field translated for the resolved locale (empty string if no translation is available).
     */
    public function getTranslatedSocialLinks(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $links = $this->getTranslation('social_links', $locale, false) ?? [];

        return array_map(function ($link) use ($locale) {
            $translatedLink = $link;

            // Translate title if it's translatable (array format)
            if (isset($link['title'])) {
                if (is_array($link['title'])) {
                    $translatedLink['title'] = $link['title'][$locale] ?? $link['title']['de'] ?? '';
                }
                // If title is already a string, keep it as is
            }

            return $translatedLink;
        }, $links);
    }

    /**
     * Return the copyright text translated for the specified locale (or the app locale).
     *
     * @param string|null $locale Locale to use for translation; when null, the application's current locale is used.
     * @return string|null The copyright text for the resolved locale, or null if no translation is available.
     */
    public function getTranslatedCopyrightText(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try to get translation for requested locale first (without fallback)
        $copyright = $this->getTranslation('copyright_text', $locale, false);
        
        // If we got a non-empty string, that means the locale exists, return it
        if (is_string($copyright) && $copyright !== '') {
            return $copyright;
        }
        
        // If we got an empty string or null, the requested locale doesn't exist
        // Fallback to 'de' translation
        if (($copyright === '' || $copyright === null) && $locale !== 'de') {
            $copyrightDe = $this->getTranslation('copyright_text', 'de', false);
            if (is_string($copyrightDe) && $copyrightDe !== '') {
                return $copyrightDe;
            }
            if (is_array($copyrightDe)) {
                return $copyrightDe['de'] ?? null;
            }
        }
        
        // If we got an array, it's the translatable structure
        // Extract the value for requested locale, fallback to 'de'
        if (is_array($copyright)) {
            // If requested locale has a non-empty value, use it
            if (isset($copyright[$locale]) && $copyright[$locale] !== '') {
                return $copyright[$locale];
            }
            // Otherwise fallback to 'de'
            return $copyright['de'] ?? null;
        }
        
        return $copyright;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}