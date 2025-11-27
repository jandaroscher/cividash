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

    $props = [
        'mode' => $mode ?? 'explore',
        'initialCategory' => $initial_category ?? null,
        'useMockData' => (bool) ($use_mock_data ?? false),
        'locale' => $locale,
        'tenantId' => $tenantId,
        'tenantSlug' => $tenantSlug,
        'pageSlug' => $page ? $page->getTranslation('slug', $locale, false) : null,
    ];
@endphp

<div
    class="tile-app-widget"
    data-vue-component="TileExplorer"
    data-props='@json($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)'
></div>


