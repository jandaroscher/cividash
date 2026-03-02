<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Navigation extends Model
{
    use BelongsToTenant;
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
        'dropdown_enabled',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'navigation_items' => 'array',
        'dropdown_enabled' => 'boolean',
    ];

    /**
     * Retrieve the singleton Navigation model for the current tenant.
     *
     * For the tenant with slug "default", prefers the legacy record with id = 1 when available;
     * if the default tenant has a record under a different id it will be migrated to id = 1
     * (reassigning any existing id = 1 that belongs to another tenant if necessary).
     * For other tenants, returns the single Navigation record associated with the tenant,
     * creating a new singleton if none exists.
     *
     * @return Navigation The singleton Navigation instance for the resolved tenant.
     *
     * @throws \App\Exceptions\InvalidTenantContextException If no tenant context is available.
     */
    public static function getInstance(): Navigation
    {
        $tenant = static::resolveTenant();

        if (! $tenant) {
            // Fallback: use default tenant
            $tenant = \App\Models\Tenant::where('slug', 'default')->first();
        }

        if (! $tenant) {
            throw new \App\Exceptions\InvalidTenantContextException('No tenant context available');
        }

        // For the default tenant, ensure id=1 in a transaction to avoid races
        if ($tenant->slug === 'default') {
            $defaultRecord = \DB::transaction(function () use ($tenant) {
                $tableName = (new static)->getTable();

                // Lock existing id=1
                $existingId1 = static::withoutGlobalScope('tenant')
                    ->where('id', 1)
                    ->lockForUpdate()
                    ->first();

                if ($existingId1 && $existingId1->tenant_id === $tenant->id) {
                    return $existingId1;
                }

                // Lock any default-tenant row
                $anyDefault = static::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->first();

                if (! $anyDefault && ! $existingId1) {
                    return null;
                }

                // Move id=1 away if owned by another tenant
                if ($existingId1 && $existingId1->tenant_id !== $tenant->id) {
                    $maxId = static::withoutGlobalScope('tenant')->lockForUpdate()->max('id') ?? 0;
                    $newId = $maxId + 1;
                    \DB::table($tableName)
                        ->where('id', 1)
                        ->update(['id' => $newId]);
                    $existingId1 = null;
                }

                // Migrate default row to id=1
                if ($anyDefault && $anyDefault->id !== 1) {
                    \DB::table($tableName)
                        ->where('id', $anyDefault->id)
                        ->update(['id' => 1]);

                    return static::withoutGlobalScope('tenant')
                        ->where('id', 1)
                        ->where('tenant_id', $tenant->id)
                        ->first();
                }

                if ($anyDefault && $anyDefault->id === 1) {
                    return $anyDefault;
                }

                return null;
            });

            if ($defaultRecord) {
                return $defaultRecord;
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
     * Retrieve the singleton Navigation model with id = 1 for the current tenant, creating it with default attributes if it does not exist.
     *
     * @return Navigation The Navigation model instance with id = 1.
     */
    public static function getOrCreateInstance(): Navigation
    {
        $tenant = static::resolveTenant();

        if (! $tenant) {
            // Fallback: use default tenant
            $tenant = \App\Models\Tenant::where('slug', 'default')->first();
        }

        if (! $tenant) {
            throw new \App\Exceptions\InvalidTenantContextException('No tenant context available');
        }

        return \DB::transaction(function () use ($tenant) {
            $tableName = (new static)->getTable();

            // Lock id=1 and any row for this tenant
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
                $existingId1 = null;
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

            // Otherwise, create id=1
            return static::unguarded(function () use ($tenant) {
                return static::withoutGlobalScope('tenant')->create([
                    'id' => 1,
                    'tenant_id' => $tenant->id,
                    'navigation_items' => [
                        'de' => [],
                        'en' => [],
                    ],
                    'dropdown_enabled' => false,
                ]);
            });
        });
    }

    /**
     * Retrieve navigation items translated for the given locale or the application's current locale.
     *
     * @param  string|null  $locale  Locale to use for translations; when null the application's current locale is used.
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
                if (isset($items[$locale]) && ! empty($items[$locale])) {
                    $items = $items[$locale];
                } else {
                    $items = $items['de'] ?? [];
                }
            }
            // Otherwise, items is already the array of navigation items for the requested locale
        }

        // If null/empty (including empty string) and locale is not 'de', try to get 'de' translation
        if ((($items === null) || ($items === '') || (is_array($items) && empty($items))) && $locale !== 'de') {
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

        // Ensure $items is an array before using array_map
        if (! is_array($items)) {
            $items = [];
        }

        // Filter out non-array entries (e.g. corrupted locale-level keys)
        $items = array_values(array_filter($items, fn ($item) => is_array($item)));

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

    /**
     * Get the tenant that owns this navigation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The tenant relation.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
