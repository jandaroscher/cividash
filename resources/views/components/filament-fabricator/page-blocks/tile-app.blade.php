@aware(['page'])

@php
    $locale = app()->getLocale();

    $tenantId = null;
    $tenantSlug = null;

    if (function_exists('tenant')) {
        try {
            $tenant = tenant();
            if ($tenant) {
                $tenantId = $tenant->id ?? null;
                $tenantSlug = $tenant->slug ?? null;
            }
        } catch (\Throwable $e) {
            // Fallback to null if tenancy context is not available
        }
    }

    // Extract values from block data - Fabricator passes data via $attributes
    // Use $attributes->get() which returns null if key doesn't exist, allowing us to distinguish
    // between "not set" (default to true) and "set to false" (use false)
    $showSearchValue = isset($attributes) && $attributes->offsetExists('show_search') 
        ? (bool) $attributes->get('show_search') 
        : true;
    $showFilterValue = isset($attributes) && $attributes->offsetExists('show_filter') 
        ? (bool) $attributes->get('show_filter') 
        : true;

    $props = [
        'showSearch' => $showSearchValue,
        'showFilter' => $showFilterValue,
        'locale' => $locale,
        'tenantId' => $tenantId,
        'tenantSlug' => $tenantSlug,
        'pageSlug' => $page ? $page->getTranslation('slug', $locale, false) : null,
    ];
    
    $propsJson = json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div
    class="tile-app-widget"
    data-vue-component="TileExplorer"
    data-props='<?php echo $propsJson; ?>'
></div>


