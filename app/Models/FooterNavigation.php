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
     * Return the tenant-scoped singleton FooterNavigation for the current tenant.
     *
     * Resolves the current tenant (falling back to the tenant with slug `default`) and returns the
     * FooterNavigation record associated with that tenant. For the `default` tenant, prefers an
     * existing record with `id = 1` and will migrate or relocate records as needed so the default
     * tenant is represented by `id = 1`. If no tenant context can be resolved, an exception is thrown.
     *
     * @return FooterNavigation The FooterNavigation instance for the resolved tenant.
     * @throws \App\Exceptions\InvalidTenantContextException If no tenant context is available.
     */
    public static function getInstance(): FooterNavigation
    {
        $tenant = static::resolveTenant();
        
        if (!$tenant) {
            // Fallback: use default tenant
            $tenant = \App\Models\Tenant::where('slug', 'default')->first();
        }
        
        if (!$tenant) {
            throw new \App\Exceptions\InvalidTenantContextException('No tenant context available');
        }
        
        // For the default tenant, ensure id=1 in a transaction to avoid races
        if ($tenant->slug === 'default') {
            $result = \DB::transaction(function () use ($tenant) {
                $tableName = (new static)->getTable();

                // Lock any existing id=1 row
                $existingId1 = static::withoutGlobalScope('tenant')
                    ->where('id', 1)
                    ->lockForUpdate()
                    ->first();

                // If id=1 already belongs to default tenant, return it
                if ($existingId1 && $existingId1->tenant_id === $tenant->id) {
                    return $existingId1;
                }

                // Lock any default-tenant row
                $anyDefault = static::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->first();

                // If no default row exists, fall through to create later
                if (! $anyDefault && ! $existingId1) {
                    return null;
                }

                // If id=1 belongs to another tenant, move it away
                if ($existingId1 && $existingId1->tenant_id !== $tenant->id) {
                    $maxId = static::withoutGlobalScope('tenant')->lockForUpdate()->max('id') ?? 0;
                    $newId = $maxId + 1;
                    \DB::table($tableName)
                        ->where('id', 1)
                        ->update(['id' => $newId]);
                }

                // If a default row exists but not id=1, migrate it
                if ($anyDefault && $anyDefault->id !== 1) {
                    \DB::table($tableName)
                        ->where('id', $anyDefault->id)
                        ->update(['id' => 1]);

                    return static::withoutGlobalScope('tenant')
                        ->where('id', 1)
                        ->where('tenant_id', $tenant->id)
                        ->first();
                }

                // If default row is already id=1, return it
                if ($anyDefault && $anyDefault->id === 1) {
                    return $anyDefault;
                }

                return null; // fall through to normal creation
            });

            if ($result) {
                return $result;
            }
        }
        
        // Find singleton for this tenant (not by id=1, but by tenant_id)
        // Since there's only one singleton per tenant, we can use first()
        $instance = static::where('tenant_id', $tenant->id)->first();
        
        if ($instance) {
            return $instance;
        }
        
        // If not found, create it via getOrCreateInstance()
        return static::getOrCreateInstance();
    }

    /**
     * Ensure a FooterNavigation exists for the resolved tenant; for the 'default' tenant, ensure the record uses id = 1, migrating any conflicting record if necessary.
     *
     * @throws \App\Exceptions\InvalidTenantContextException If no tenant context can be resolved.
     * @return FooterNavigation The FooterNavigation instance for the resolved tenant.
     */
    public static function getOrCreateInstance(): FooterNavigation
    {
        $tenant = static::resolveTenant();
        
        if (!$tenant) {
            // Fallback: use default tenant
            $tenant = \App\Models\Tenant::where('slug', 'default')->first();
        }
        
        if (!$tenant) {
            throw new \App\Exceptions\InvalidTenantContextException('No tenant context available');
        }
        
        // Default tenant: enforce id=1 with transactional migration
        if ($tenant->slug === 'default') {
            return \DB::transaction(function () use ($tenant) {
                $tableName = (new static)->getTable();

                // Lock any id=1 row and any row for this tenant
                $existingId1 = static::withoutGlobalScope('tenant')
                    ->where('id', 1)
                    ->lockForUpdate()
                    ->first();

                $tenantRow = static::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->first();

                // If tenant already has id=1, return it
                if ($tenantRow && $tenantRow->id === 1) {
                    return $tenantRow;
                }

                // If id=1 belongs to another tenant, move it away
                if ($existingId1 && (! $tenantRow || $existingId1->tenant_id !== $tenant->id)) {
                    $maxId = static::withoutGlobalScope('tenant')->lockForUpdate()->max('id') ?? 0;
                    $newId = $maxId + 1;
                    \DB::table($tableName)
                        ->where('id', 1)
                        ->update(['id' => $newId]);
                }

                // If tenant has a row but not id=1, migrate it
                if ($tenantRow && $tenantRow->id !== 1) {
                    \DB::table($tableName)
                        ->where('id', $tenantRow->id)
                        ->update(['id' => 1]);

                    return static::withoutGlobalScope('tenant')
                        ->where('id', 1)
                        ->where('tenant_id', $tenant->id)
                        ->first();
                }

                // Otherwise, create id=1 for this tenant
                return static::unguarded(function () use ($tenant) {
                    return static::withoutGlobalScope('tenant')->create([
                        'id' => 1,
                        'tenant_id' => $tenant->id,
                        'footer_navigation_items' => [
                            'de' => [],
                            'en' => [],
                        ],
                        'social_links' => [
                            'de' => [],
                            'en' => [],
                        ],
                        'layout_type' => 'single-row',
                        'columns' => 3,
                        'social_links_enabled' => true,
                        'copyright_text' => [
                            'de' => '',
                            'en' => '',
                        ],
                    ]);
                });
            });
        }

        // Non-default tenants: create/return tenant-scoped record without id=1 migration
        return static::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'footer_navigation_items' => [
                    'de' => [],
                    'en' => [],
                ],
                'social_links' => [
                    'de' => [],
                    'en' => [],
                ],
                'layout_type' => 'single-row',
                'columns' => 3,
                'social_links_enabled' => true,
                'copyright_text' => [
                    'de' => '',
                    'en' => '',
                ],
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
        
        // If null/empty (including empty string) and locale is not 'de', try to get 'de' translation
        if ((($items === null) || ($items === '') || (is_array($items) && empty($items))) && $locale !== 'de') {
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
        
        // Ensure $items is an array before using array_map
        if (!is_array($items)) {
            $items = [];
        }

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
        $links = $this->getTranslation('social_links', $locale, false);

        // Support both translation-structure and direct array storage
        if (is_array($links)) {
            if (isset($links[$locale]) || isset($links['de']) || isset($links['en'])) {
                // Translation structure
                if (isset($links[$locale]) && !empty($links[$locale])) {
                    $links = $links[$locale];
                } else {
                    $links = $links['de'] ?? [];
                }
            }
        }

        // Fallback to 'de' when requested locale has nothing
        if ((($links === null) || ($links === '') || (is_array($links) && empty($links))) && $locale !== 'de') {
            $linksDe = $this->getTranslation('social_links', 'de', false);
            if (is_array($linksDe)) {
                if (isset($linksDe['de']) || isset($linksDe['en'])) {
                    $links = $linksDe['de'] ?? [];
                } else {
                    $links = $linksDe;
                }
            } else {
                $links = $linksDe ?? [];
            }
        }

        $links = $links ?? [];

        // Ensure $links is an array before using array_map
        if (!is_array($links)) {
            $links = [];
        }

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
         * Resolve the copyright text for the given locale, falling back to German ('de') when a translation is not available.
         *
         * If the stored value is a translatable structure (array), the function returns the entry for the requested locale or the 'de' entry as a fallback. If the stored value is a non-empty string for the requested locale, that string is returned. When no translation can be resolved, `null` is returned.
         *
         * @param string|null $locale Locale to use for translation; when null, the application's current locale is used.
         * @return string|null The resolved copyright text for the locale, or null if no translation is available.
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

    /**
     * Define the owning tenant relationship for the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relation linking this record to a Tenant.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}