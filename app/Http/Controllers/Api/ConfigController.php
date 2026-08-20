<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContentRequest;
use App\Http\Requests\Admin\UpdateDashboardRequest;
use App\Http\Requests\Admin\UpdateFooterRequest;
use App\Http\Requests\Admin\UpdateGeneralRequest;
use App\Http\Requests\Admin\UpdateNavigationRequest;
use App\Http\Requests\UpdateBrandingRequest;
use App\Models\CategoryGroup;
use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Tenant;
use App\Settings\BrandingSettings;
use App\Settings\ContentSettings;
use App\Settings\DashboardSettings;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Configuration API endpoints for branding, general settings, header, footer, and tenant information.
 */
class ConfigController extends Controller
{
    protected static ?bool $pageHasIsPublic = null;

    /**
     * Retrieve the tenant's branding and styling configuration.
     *
     * Includes colors, typography, logo, and other visual settings. Stored file paths
     * (logo and custom font file) are converted to public URLs or null when absent.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @response 200 scenario="Branding config" {"data": {"primary_color": "#0d47a1", "secondary_color": "#1976d2", "logo_url": "https://example.com/storage/logos/logo.png", "accent_color": "#ff9800", "typography_font_family": "Inter", "typography_font_weights": ["400", "600", "700"], "slider_colors": ["#0d47a1", "#1976d2"], "background_color": "#ffffff", "card_background_color": "#f5f5f5", "hero_background_color": "#e3f2fd", "overlay_background_color": "rgba(0,0,0,0.5)", "header_background_color": "#ffffff", "footer_background_color": "#f5f5f5", "text_primary_color": "#212121", "text_secondary_color": "#757575", "text_inverse_color": "#ffffff", "link_color": "#0d47a1", "link_hover_color": "#1565c0", "border_color": "#e0e0e0", "divider_color": "#bdbdbd", "shadow_color": "rgba(0,0,0,0.1)", "nav_text_color": "#212121", "nav_text_color_inactive": "#757575", "nav_hover_color": "#0d47a1", "typography_font_sizes": {"small": "0.875rem", "base": "1rem", "large": "1.25rem"}, "typography_custom_font_name": null, "typography_custom_font_file": null}}
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource The branding configuration as a JSON resource, with file paths converted to public URLs or null.
     */
    public function branding(): JsonResource
    {
        $settings = app(BrandingSettings::class);

        return new JsonResource([
            'primary_color' => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            // Convert the stored path into a public URL:
            'logo_url' => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
            'accent_color' => $settings->accent_color,
            'typography_font_family' => $settings->typography_font_family,
            'typography_font_weights' => $settings->typography_font_weights,
            'slider_colors' => $settings->slider_colors,
            'background_color' => $settings->background_color,
            'card_background_color' => $settings->card_background_color,
            'hero_background_color' => $settings->hero_background_color,
            'overlay_background_color' => $settings->overlay_background_color,
            'header_background_color' => $settings->header_background_color,
            'footer_background_color' => $settings->footer_background_color,
            'text_primary_color' => $settings->text_primary_color,
            'text_secondary_color' => $settings->text_secondary_color,
            'text_inverse_color' => $settings->text_inverse_color,
            'link_color' => $settings->link_color,
            'link_hover_color' => $settings->link_hover_color,
            'border_color' => $settings->border_color,
            'divider_color' => $settings->divider_color,
            'shadow_color' => $settings->shadow_color,
            'nav_text_color' => $settings->nav_text_color,
            'nav_text_color_inactive' => $settings->nav_text_color_inactive,
            'nav_hover_color' => $settings->nav_hover_color,
            'typography_font_sizes' => $settings->typography_font_sizes,
            'typography_custom_font_name' => $settings->typography_custom_font_name,
            'typography_custom_font_file' => $settings->typography_custom_font_file
                ? Storage::disk('public')->url($settings->typography_custom_font_file)
                : null,
            'tile_color_source_group_key' => $this->resolveCategoryGroupKey($settings->tile_color_source_group_id),
            'tile_background_category_group_key' => $this->resolveCategoryGroupKey($settings->tile_background_category_group_id),
            'card_radius' => $settings->card_radius,
            'card_border_width' => $settings->card_border_width,
            'card_border_color' => $settings->card_border_color,
        ]);
    }

    /**
     * Retrieve site-wide general configuration such as site name and favicon URL.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @response 200 scenario="General config" {"data": {"site_name": "Zukunftsbarometer Regensburg", "favicon_url": "https://example.com/storage/branding/favicon.png"}}
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource The general configuration containing `site_name` (string) and `favicon_url` (string|null).
     */
    public function general(): JsonResource
    {
        $settings = app(GeneralSettings::class);

        return new JsonResource([
            'site_name' => $settings->site_name,
            'site_active' => $settings->site_active,
            'favicon_url' => $settings->favicon
                ? Storage::disk('public')->url($settings->favicon)
                : null,
        ]);
    }

    /**
     * Retrieve header configuration including translated navigation items and UI toggles.
     *
     * Navigation items that reference pages not considered active or that are explicitly marked inactive are removed.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @queryParam locale string Locale for translations (`de` or `en`). Example: de
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource JSON resource with:
     *                                                      - `navigation_items`: array of navigation items (filtered and translated),
     *                                                      - `dropdown_enabled`: boolean,
     *                                                      - `english_translation_active`: boolean
     */
    public function header(\Illuminate\Http\Request $request): JsonResource
    {
        // Set tenant context from request if available
        $tenant = $this->resolveTenantFromRequest($request);
        if ($tenant) {
            try {
                Filament::setTenant($tenant);
            } catch (\Throwable $e) {
                // Filament might not be initialized, continue anyway
            }
        }

        // Try to get existing instance first, create if it doesn't exist
        try {
            $navigation = Navigation::getInstance();
        } catch (\Throwable $e) {
            $navigation = Navigation::getOrCreateInstance();
        }

        // Get locale from request parameter or use app locale
        $locale = $request->query('locale', app()->getLocale());

        // Validate locale
        if (! in_array($locale, ['de', 'en'])) {
            $locale = app()->getLocale();
        }

        $navigationItems = $navigation->getTranslatedNavigationItems($locale);
        $activePageIds = $this->getActivePageIds($navigationItems);
        $filteredItems = $this->filterItemsByActivePages($navigationItems, $activePageIds);
        $filteredItems = $this->filterInactiveItems($filteredItems);

        $settings = app(GeneralSettings::class);

        return new JsonResource([
            'navigation_items' => $filteredItems,
            'dropdown_enabled' => $navigation->dropdown_enabled,
            'english_translation_active' => $settings->english_translation_active,
        ]);
    }

    /**
     * Retrieve the tenant's footer configuration.
     *
     * Returns navigation items, social links, layout settings, column count, whether social links are enabled,
     * and the translated copyright text. Navigation items and social links that reference inactive pages or are
     * explicitly marked inactive are removed.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @queryParam locale string Locale for translations (de or en). Example: de
     *
     * @response 200 scenario="Footer config" {"data": {"footer_navigation_items": [{"type": "page", "page_id": 2, "label": "Impressum"}], "social_links": [{"platform": "twitter", "url": "https://twitter.com/example"}], "layout_type": "columns", "columns": 3, "social_links_enabled": true, "copyright_text": "© 2025 Stadt Regensburg"}}
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource JSON resource containing the footer configuration.
     */
    public function footer(\Illuminate\Http\Request $request): JsonResource
    {
        // Set tenant context from request if available
        $tenant = $this->resolveTenantFromRequest($request);
        if ($tenant) {
            try {
                Filament::setTenant($tenant);
            } catch (\Throwable $e) {
                // Filament might not be initialized, continue anyway
            }
        }

        // Try to get existing instance first, create if it doesn't exist
        try {
            $footer = FooterNavigation::getInstance();
        } catch (\Throwable $e) {
            $footer = FooterNavigation::getOrCreateInstance();
        }

        // Get locale from request parameter or use app locale
        $locale = $request->query('locale', app()->getLocale());

        // Validate locale
        if (! in_array($locale, ['de', 'en'])) {
            $locale = app()->getLocale();
        }

        $footerItems = $footer->getTranslatedFooterNavigationItems($locale);
        $activePageIds = $this->getActivePageIds($footerItems);
        $filteredFooterItems = $this->filterItemsByActivePages($footerItems, $activePageIds);
        $filteredFooterItems = $this->filterInactiveItems($filteredFooterItems);

        $socialLinks = $footer->getTranslatedSocialLinks($locale) ?? [];
        $filteredSocialLinks = $this->filterInactiveItems($socialLinks);

        $sponsors = $footer->getTranslatedSponsors($locale) ?? [];

        return new JsonResource([
            'footer_navigation_items' => $filteredFooterItems,
            'social_links' => $filteredSocialLinks,
            'layout_type' => $footer->layout_type,
            'columns' => $footer->columns,
            'social_links_enabled' => $footer->social_links_enabled,
            'copyright_text' => $footer->getTranslatedCopyrightText($locale),
            'sponsors' => $sponsors,
        ]);
    }

    /**
     * Collects all page IDs referenced by navigation or footer items recursively.
     *
     * @param  array  $items  Array of navigation/footer items; items may contain 'type', 'page_id', and nested 'children'.
     * @return array<int> Unique page IDs referenced by the items as integers.
     */
    protected function collectPageIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'page' && isset($item['page_id'])) {
                $ids[] = (int) $item['page_id'];
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $ids = array_merge($ids, $this->collectPageIds($item['children']));
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Determine which page IDs referenced by the given navigation items are considered active.
     *
     * Collects page IDs from the provided items and, if the Page model defines an `is_public`
     * column, returns only those IDs whose pages have `is_public` set to true. If the model
     * does not have the `is_public` column, returns all collected page IDs. Returns an empty
     * array when no page IDs are found.
     *
     * @param  array  $items  Navigation or footer items to scan for `page_id` values.
     * @return array<int> The active page IDs as integers; empty array if none are active or found.
     */
    protected function getActivePageIds(array $items): array
    {
        $pageIds = $this->collectPageIds($items);

        if (empty($pageIds)) {
            return [];
        }

        if (! static::pageHasIsPublicColumn()) {
            return $pageIds;
        }

        return \App\Models\Page::query()
            ->whereIn('id', $pageIds)
            ->where('is_public', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Determine whether the `pages` database table contains an `is_public` column and cache the result.
     *
     * The result is stored in the static::$pageHasIsPublic cache to avoid repeated schema checks.
     *
     * @return bool `true` if the Page table has an `is_public` column, `false` otherwise.
     */
    protected static function pageHasIsPublicColumn(): bool
    {
        if (static::$pageHasIsPublic !== null) {
            return static::$pageHasIsPublic;
        }

        $pageTable = (new \App\Models\Page)->getTable();
        static::$pageHasIsPublic = \Illuminate\Support\Facades\Schema::hasColumn($pageTable, 'is_public');

        return static::$pageHasIsPublic;
    }

    /**
     * Remove items explicitly marked as inactive.
     *
     * Recursively removes any item with an `is_active` key set to `false`.
     * Items lacking an `is_active` key are preserved for backward compatibility.
     *
     * @param  array  $items  Array of item arrays; each item may contain an `is_active` boolean and a `children` array.
     * @return array The input items with inactive entries removed and children recursively filtered.
     */
    protected function filterInactiveItems(array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            // Skip items that are explicitly deactivated (handles false, 0, "0")
            if (array_key_exists('is_active', $item) && ! $item['is_active']) {
                continue;
            }

            // Recursively filter children
            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterInactiveItems($item['children']);
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Remove navigation items that reference pages not in the provided active page ID list.
     *
     * Filters items recursively; items of type "page" whose page_id is missing or not present
     * in $activePageIds are removed. Parent items are retained even if their children list
     * becomes empty.
     *
     * @param  array<int,mixed>  $items  List of navigation/footer items to filter.
     * @param  array<int>  $activePageIds  Page IDs considered active.
     * @return array<int,mixed> The filtered list of items.
     */
    protected function filterItemsByActivePages(array $items, array $activePageIds): array
    {
        $filtered = [];

        foreach ($items as $item) {
            $type = $item['type'] ?? null;
            $pageId = isset($item['page_id']) ? (int) $item['page_id'] : null;

            if ($type === 'page' && (! $pageId || ! in_array($pageId, $activePageIds, true))) {
                continue;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterItemsByActivePages($item['children'], $activePageIds);
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Get tenant information
     *
     * Returns the resolved tenant's public configuration. Shows how the tenant was resolved
     * (via token, domain, or default fallback) in the `resolved_by` field.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @response 200 scenario="Tenant resolved via domain" {"data": {"slug": "demo-city", "name": "Demo City", "domain": "demo-city.example.org", "frontend_base_url": "https://demo-city.example.org", "resolved_by": "domain", "theme_slug": "demo-city"}}
     * @response 200 scenario="Tenant resolved via token" {"data": {"slug": "demo-city", "name": "Demo City", "domain": "demo-city.example.org", "frontend_base_url": "https://demo-city.example.org", "resolved_by": "token", "theme_slug": "demo-city"}}
     * @response 200 scenario="Default tenant fallback" {"data": {"slug": "default", "name": "Default", "domain": null, "frontend_base_url": null, "resolved_by": "default", "theme_slug": null}}
     */
    public function tenant(\Illuminate\Http\Request $request): JsonResource
    {
        $tenant = $request->attributes->get('resolved_tenant');
        $resolvedBy = $request->attributes->get('resolved_tenant_by', 'default');

        if (! $tenant) {
            // Fallback to default tenant
            $tenant = Tenant::where('slug', 'default')->first();
            $resolvedBy = 'default';
        }

        return new JsonResource([
            'slug' => $tenant?->slug,
            'name' => $tenant?->name,
            'domain' => $tenant?->domain,
            'frontend_base_url' => $tenant?->frontend_base_url,
            'resolved_by' => $resolvedBy,
            'theme_slug' => $tenant?->theme?->slug,
        ]);
    }

    /**
     * Retrieve dashboard meta configuration.
     *
     * Returns contact details, documentation URLs, and display preferences.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @response 200 scenario="Dashboard config" {"data": {"open_source_docs_url": "https://example.com/docs", "user_manual_url": null, "contact_name": "Max Mustermann", "contact_email": "max@example.com", "contact_url": null, "made_with_text": "Made with love", "show_server_time": false}}
     */
    public function dashboard(): JsonResource
    {
        $settings = app(DashboardSettings::class);

        return new JsonResource([
            'open_source_docs_url' => $settings->open_source_docs_url,
            'user_manual_url' => $settings->user_manual_url,
            'contact_name' => $settings->contact_name,
            'contact_email' => $settings->contact_email,
            'contact_url' => $settings->contact_url,
            'made_with_text' => $settings->made_with_text,
            'show_server_time' => $settings->show_server_time,
        ]);
    }

    /**
     * Retrieve hero content blocks.
     *
     * Returns content settings including hero section blocks.
     *
     * @group Public API - Configuration
     *
     * @unauthenticated
     *
     * @response 200 scenario="Content config" {"data": {"hero_content": []}}
     */
    public function content(): JsonResource
    {
        $settings = app(ContentSettings::class);

        return new JsonResource([
            'hero_content' => $settings->hero_content,
        ]);
    }

    /**
     * Partially update navigation settings.
     *
     * Only provided fields are updated. Requires admin-api permission and an explicit tenant context.
     *
     * @group Admin API - Navigation Configuration
     *
     * @authenticated
     *
     * @bodyParam navigation_items object Navigation items per locale. Example: {"de": [{"label": "Start", "url": "/"}], "en": [{"label": "Home", "url": "/en"}]}
     * @bodyParam dropdown_enabled boolean Enable dropdown menus. Example: false
     *
     * @response 200 scenario="Navigation updated" {"data": {"navigation_items": {"de": [], "en": []}, "dropdown_enabled": false}}
     */
    public function updateNavigation(UpdateNavigationRequest $request): JsonResource
    {
        $navigation = Navigation::getOrCreateInstance();
        $validated = $request->validated();

        if (array_key_exists('navigation_items', $validated)) {
            $navigation->navigation_items = $validated['navigation_items'];
        }

        if (array_key_exists('dropdown_enabled', $validated)) {
            $navigation->dropdown_enabled = $validated['dropdown_enabled'];
        }

        $navigation->save();

        return new JsonResource([
            'navigation_items' => $navigation->getTranslations('navigation_items'),
            'dropdown_enabled' => $navigation->dropdown_enabled,
        ]);
    }

    /**
     * Partially update footer configuration.
     *
     * Only provided fields are updated. Requires admin-api permission and an explicit tenant context.
     *
     * @group Admin API - Footer Configuration
     *
     * @authenticated
     *
     * @bodyParam footer_navigation_items object Footer navigation items per locale. Example: {"de": [], "en": []}
     * @bodyParam social_links object Social links per locale. Example: {"de": [], "en": []}
     * @bodyParam layout_type string Footer layout type. Example: single-row
     * @bodyParam columns integer Number of footer columns (1-6). Example: 3
     * @bodyParam social_links_enabled boolean Enable social links display. Example: true
     * @bodyParam copyright_text object Copyright text per locale. Example: {"de": "© 2025", "en": "© 2025"}
     * @bodyParam sponsors object Sponsors per locale. Example: {"de": [{"image": "footer-sponsors/logo.png", "url": "https://example.com", "name": "Example"}], "en": []}
     *
     * @response 200 scenario="Footer updated" {"data": {"footer_navigation_items": {"de": [], "en": []}, "social_links": {"de": [], "en": []}, "layout_type": "single-row", "columns": 3, "social_links_enabled": true, "copyright_text": {"de": "", "en": ""}}}
     */
    public function updateFooter(UpdateFooterRequest $request): JsonResource
    {
        $footer = FooterNavigation::getOrCreateInstance();
        $validated = $request->validated();

        if (array_key_exists('footer_navigation_items', $validated)) {
            $footer->footer_navigation_items = $validated['footer_navigation_items'];
        }

        if (array_key_exists('social_links', $validated)) {
            $footer->social_links = $validated['social_links'];
        }

        if (array_key_exists('layout_type', $validated)) {
            $footer->layout_type = $validated['layout_type'];
        }

        if (array_key_exists('columns', $validated)) {
            $footer->columns = $validated['columns'];
        }

        if (array_key_exists('social_links_enabled', $validated)) {
            $footer->social_links_enabled = $validated['social_links_enabled'];
        }

        if (array_key_exists('copyright_text', $validated)) {
            $footer->copyright_text = $validated['copyright_text'];
        }

        if (array_key_exists('sponsors', $validated)) {
            $existing = $footer->getTranslations('sponsors');
            $footer->sponsors = array_merge($existing, $validated['sponsors']);
        }

        $footer->save();

        return new JsonResource([
            'footer_navigation_items' => $footer->getTranslations('footer_navigation_items'),
            'social_links' => $footer->getTranslations('social_links'),
            'layout_type' => $footer->layout_type,
            'columns' => $footer->columns,
            'social_links_enabled' => $footer->social_links_enabled,
            'copyright_text' => $footer->getTranslations('copyright_text'),
            'sponsors' => $footer->getTranslations('sponsors'),
        ]);
    }

    /**
     * Partially update general site settings.
     *
     * Only provided fields are updated. Requires admin-api permission and an explicit tenant context.
     *
     * @group Admin API - General Configuration
     *
     * @authenticated
     *
     * @bodyParam site_name string Site name. Example: Zukunftsbarometer Regensburg
     * @bodyParam site_active boolean Whether the site is active. Example: true
     * @bodyParam favicon string Favicon file path. Example: branding/favicon.png
     *
     * @response 200 scenario="General settings updated" {"data": {"site_name": "Zukunftsbarometer Regensburg", "site_active": true, "favicon_url": "https://example.com/storage/branding/favicon.png"}}
     */
    public function updateGeneral(UpdateGeneralRequest $request): JsonResource
    {
        $settings = app(GeneralSettings::class);
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        $settings->save();

        // Reload settings to get updated values
        $settings = app(GeneralSettings::class);

        return new JsonResource([
            'site_name' => $settings->site_name,
            'site_active' => $settings->site_active,
            'favicon_url' => $settings->favicon
                ? Storage::disk('public')->url($settings->favicon)
                : null,
        ]);
    }

    /**
     * Partially update dashboard meta settings.
     *
     * Only provided fields are updated. Requires admin-api permission and an explicit tenant context.
     *
     * @group Admin API - Dashboard Configuration
     *
     * @authenticated
     *
     * @bodyParam open_source_docs_url string URL to open source documentation. Example: https://example.com/docs
     * @bodyParam user_manual_url string URL to user manual. Example: https://example.com/manual
     * @bodyParam contact_name string Contact person name. Example: Max Mustermann
     * @bodyParam contact_email string Contact email address. Example: max@example.com
     * @bodyParam contact_url string Contact URL. Example: https://example.com/contact
     * @bodyParam made_with_text string Made-with attribution text. Example: Made with love
     * @bodyParam show_server_time boolean Show server time in dashboard. Example: false
     *
     * @response 200 scenario="Dashboard settings updated" {"data": {"open_source_docs_url": "https://example.com/docs", "user_manual_url": null, "contact_name": "Max Mustermann", "contact_email": "max@example.com", "contact_url": null, "made_with_text": "Made with love", "show_server_time": false}}
     */
    public function updateDashboard(UpdateDashboardRequest $request): JsonResource
    {
        $settings = app(DashboardSettings::class);
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        $settings->save();

        // Reload settings to get updated values
        $settings = app(DashboardSettings::class);

        return new JsonResource([
            'open_source_docs_url' => $settings->open_source_docs_url,
            'user_manual_url' => $settings->user_manual_url,
            'contact_name' => $settings->contact_name,
            'contact_email' => $settings->contact_email,
            'contact_url' => $settings->contact_url,
            'made_with_text' => $settings->made_with_text,
            'show_server_time' => $settings->show_server_time,
        ]);
    }

    /**
     * Partially update hero content settings.
     *
     * Only provided fields are updated. Requires admin-api permission and an explicit tenant context.
     *
     * @group Admin API - Content Configuration
     *
     * @authenticated
     *
     * @bodyParam hero_content array Hero content blocks. Example: [{"type": "text", "content": "Welcome"}]
     *
     * @response 200 scenario="Content settings updated" {"data": {"hero_content": []}}
     */
    public function updateContent(UpdateContentRequest $request): JsonResource
    {
        $settings = app(ContentSettings::class);
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        $settings->save();

        // Reload settings to get updated values
        $settings = app(ContentSettings::class);

        return new JsonResource([
            'hero_content' => $settings->hero_content,
        ]);
    }

    /**
     * Determine the Tenant context from the incoming request.
     *
     * Checks the `tenant` query parameter first, then the `X-Tenant` header; accepts either a numeric id or a slug.
     * If no tenant is found, returns the tenant with slug "default" when present.
     *
     * @param  \Illuminate\Http\Request  $request  The current HTTP request.
     * @return \App\Models\Tenant|null The resolved Tenant model, the tenant with slug "default" if none was specified, or `null` if no default tenant exists.
     */
    protected function resolveTenantFromRequest(\Illuminate\Http\Request $request): ?Tenant
    {
        // Priority 1: Use tenant already resolved by ResolveTenantFromRequest middleware
        // (handles Bearer Token > Domain matching > Default fallback)
        $resolvedTenant = $request->attributes->get('resolved_tenant');
        if ($resolvedTenant) {
            return $resolvedTenant;
        }

        // Try query parameter first
        if ($request->has('tenant')) {
            $tenantIdentifier = $request->input('tenant');
            $tenant = null;

            if (is_numeric($tenantIdentifier)) {
                $tenant = Tenant::find($tenantIdentifier);
            } else {
                $tenant = Tenant::where('slug', $tenantIdentifier)->first();
            }

            if ($tenant) {
                return $tenant;
            }
        }

        // Try header
        if ($request->hasHeader('X-Tenant')) {
            $tenantIdentifier = $request->header('X-Tenant');
            $tenant = null;

            if (is_numeric($tenantIdentifier)) {
                $tenant = Tenant::find($tenantIdentifier);
            } else {
                $tenant = Tenant::where('slug', $tenantIdentifier)->first();
            }

            if ($tenant) {
                return $tenant;
            }
        }

        // Fallback to default tenant
        return Tenant::where('slug', 'default')->first();
    }

    /**
     * Partially update the tenant's branding settings.
     *
     * Only provided fields are updated. Requires admin-api permission and an explicit tenant context.
     *
     * @group Admin API - Branding Configuration
     *
     * @authenticated
     *
     * @param  \App\Http\Requests\UpdateBrandingRequest  $request  The validated request containing branding fields to update.
     * @return \Illuminate\Http\Resources\Json\JsonResource The updated branding configuration formatted for API responses.
     *
     * @bodyParam primary_color string Primary brand color (hex). Example: #0d47a1
     * @bodyParam secondary_color string Secondary brand color (hex). Example: #1976d2
     * @bodyParam accent_color string Accent color (hex). Example: #ff9800
     * @bodyParam background_color string Page background color. Example: #ffffff
     * @bodyParam card_background_color string Card background color. Example: #f5f5f5
     * @bodyParam header_background_color string Header background color. Example: #ffffff
     * @bodyParam footer_background_color string Footer background color. Example: #f5f5f5
     * @bodyParam text_primary_color string Primary text color. Example: #212121
     * @bodyParam text_secondary_color string Secondary text color. Example: #757575
     * @bodyParam link_color string Link color. Example: #0d47a1
     * @bodyParam typography_font_family string Font family name. Example: Inter
     *
     * @response 200 scenario="Branding updated" {"data": {"primary_color": "#0d47a1", "secondary_color": "#1976d2", "logo_url": null, "accent_color": "#ff9800", "typography_font_family": "Inter", "typography_font_weights": ["400", "600", "700"], "slider_colors": ["#0d47a1", "#1976d2"], "background_color": "#ffffff", "card_background_color": "#f5f5f5", "hero_background_color": "#e3f2fd", "overlay_background_color": "rgba(0,0,0,0.5)", "header_background_color": "#ffffff", "footer_background_color": "#f5f5f5", "text_primary_color": "#212121", "text_secondary_color": "#757575", "text_inverse_color": "#ffffff", "link_color": "#0d47a1", "link_hover_color": "#1565c0", "border_color": "#e0e0e0", "divider_color": "#bdbdbd", "shadow_color": "rgba(0,0,0,0.1)", "nav_text_color": "#212121", "nav_text_color_inactive": "#757575", "nav_hover_color": "#0d47a1", "typography_font_sizes": {"small": "0.875rem", "base": "1rem", "large": "1.25rem"}, "typography_custom_font_name": null, "typography_custom_font_file": null}}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     * @response 422 scenario="Validation error" {"message": "The primary color must be a valid hex color.", "errors": {"primary_color": ["The primary color must be a valid hex color."]}}
     */
    public function updateBranding(UpdateBrandingRequest $request): JsonResource
    {
        $settings = app(BrandingSettings::class);
        $validated = $request->validated();

        // Update only provided fields
        foreach ($validated as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        $settings->save();

        // Reload settings to get updated values
        $settings = app(BrandingSettings::class);

        // Return updated settings in the same format as GET
        return new JsonResource([
            'primary_color' => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            'logo_url' => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
            'accent_color' => $settings->accent_color,
            'typography_font_family' => $settings->typography_font_family,
            'typography_font_weights' => $settings->typography_font_weights,
            'slider_colors' => $settings->slider_colors,
            'background_color' => $settings->background_color,
            'card_background_color' => $settings->card_background_color,
            'hero_background_color' => $settings->hero_background_color,
            'overlay_background_color' => $settings->overlay_background_color,
            'header_background_color' => $settings->header_background_color,
            'footer_background_color' => $settings->footer_background_color,
            'text_primary_color' => $settings->text_primary_color,
            'text_secondary_color' => $settings->text_secondary_color,
            'text_inverse_color' => $settings->text_inverse_color,
            'link_color' => $settings->link_color,
            'link_hover_color' => $settings->link_hover_color,
            'border_color' => $settings->border_color,
            'divider_color' => $settings->divider_color,
            'shadow_color' => $settings->shadow_color,
            'nav_text_color' => $settings->nav_text_color,
            'nav_text_color_inactive' => $settings->nav_text_color_inactive,
            'nav_hover_color' => $settings->nav_hover_color,
            'typography_font_sizes' => $settings->typography_font_sizes,
            'typography_custom_font_name' => $settings->typography_custom_font_name,
            'typography_custom_font_file' => $settings->typography_custom_font_file
                ? Storage::disk('public')->url($settings->typography_custom_font_file)
                : null,
            'tile_color_source_group_key' => $this->resolveCategoryGroupKey($settings->tile_color_source_group_id),
            'tile_background_category_group_key' => $this->resolveCategoryGroupKey($settings->tile_background_category_group_id),
            'card_radius' => $settings->card_radius,
            'card_border_width' => $settings->card_border_width,
            'card_border_color' => $settings->card_border_color,
        ]);
    }

    private function resolveCategoryGroupKey(?int $id): ?string
    {
        return $id ? CategoryGroup::find($id)?->key : null;
    }
}
