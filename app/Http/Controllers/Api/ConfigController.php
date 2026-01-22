<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandingRequest;
use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Tenant;
use App\Settings\BrandingSettings;
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
     * Get branding configuration
     *
     * Returns the tenant's branding and styling configuration including colors, typography,
     * logo, and other visual settings. File paths are converted to public URLs.
     *
     * @group Public API - Configuration
     * @unauthenticated
     *
     * @response 200 scenario="Branding config" {"data": {"primary_color": "#0d47a1", "secondary_color": "#1976d2", "logo_url": "https://example.com/storage/logos/logo.png", "accent_color": "#ff9800", "typography_font_family": "Inter", "typography_font_weights": ["400", "600", "700"], "slider_colors": ["#0d47a1", "#1976d2"], "background_color": "#ffffff", "card_background_color": "#f5f5f5", "hero_background_color": "#e3f2fd", "overlay_background_color": "rgba(0,0,0,0.5)", "header_background_color": "#ffffff", "footer_background_color": "#f5f5f5", "text_primary_color": "#212121", "text_secondary_color": "#757575", "text_inverse_color": "#ffffff", "link_color": "#0d47a1", "link_hover_color": "#1565c0", "border_color": "#e0e0e0", "divider_color": "#bdbdbd", "shadow_color": "rgba(0,0,0,0.1)", "nav_text_color": "#212121", "nav_text_color_inactive": "#757575", "nav_hover_color": "#0d47a1", "typography_font_sizes": {"small": "0.875rem", "base": "1rem", "large": "1.25rem"}, "typography_custom_font_name": null, "typography_custom_font_file": null}}
     */
    public function branding(): JsonResource
    {
        $settings = app(BrandingSettings::class);

        return new JsonResource([
            'primary_color'   => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            // Convert the stored path into a public URL:
            'logo_url'        => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
            'accent_color'    => $settings->accent_color,
            'typography_font_family' => $settings->typography_font_family,
            'typography_font_weights' => $settings->typography_font_weights,
            'slider_colors'   => $settings->slider_colors,
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
        ]);
    }

    /**
     * Get general configuration
     *
     * Returns site-wide general configuration values like site name and active status.
     *
     * @group Public API - Configuration
     * @unauthenticated
     *
     * @response 200 scenario="General config" {"data": {"site_name": "Zukunftsbarometer Regensburg", "site_active": true}}
     */
    public function general(): JsonResource
    {
        $settings = app(GeneralSettings::class);

        return new JsonResource([
            'site_name'   => $settings->site_name,
            'site_active' => $settings->site_active,
        ]);
    }

    /**
     * Get header configuration
     *
     * Returns header configuration including translated navigation items and UI toggles.
     * Navigation items referencing inactive pages are filtered out.
     *
     * @group Public API - Configuration
     * @unauthenticated
     *
     * @queryParam locale string Locale for translations (de or en). Example: de
     *
     * @response 200 scenario="Header config" {"data": {"navigation_items": [{"type": "page", "page_id": 1, "label": "Start", "url": "/"}], "show_language_switcher": true, "dropdown_enabled": false}}
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
        if (!in_array($locale, ['de', 'en'])) {
            $locale = app()->getLocale();
        }

        $navigationItems = $navigation->getTranslatedNavigationItems($locale);
        $activePageIds = $this->getActivePageIds($navigationItems);
        $filteredItems = $this->filterItemsByActivePages($navigationItems, $activePageIds);

        return new JsonResource([
            'navigation_items' => $filteredItems,
            'show_language_switcher' => $navigation->show_language_switcher,
            'dropdown_enabled' => $navigation->dropdown_enabled,
        ]);
    }

    /**
     * Get footer configuration
     *
     * Returns footer configuration including navigation items, social links, layout settings,
     * and copyright text. Navigation items referencing inactive pages are filtered out.
     *
     * @group Public API - Configuration
     * @unauthenticated
     *
     * @queryParam locale string Locale for translations (de or en). Example: de
     *
     * @response 200 scenario="Footer config" {"data": {"footer_navigation_items": [{"type": "page", "page_id": 2, "label": "Impressum"}], "social_links": [{"platform": "twitter", "url": "https://twitter.com/example"}], "layout_type": "columns", "columns": 3, "social_links_enabled": true, "copyright_text": "© 2025 Stadt Regensburg"}}
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
        if (!in_array($locale, ['de', 'en'])) {
            $locale = app()->getLocale();
        }

        $footerItems = $footer->getTranslatedFooterNavigationItems($locale);
        $activePageIds = $this->getActivePageIds($footerItems);
        $filteredFooterItems = $this->filterItemsByActivePages($footerItems, $activePageIds);

        return new JsonResource([
            'footer_navigation_items' => $filteredFooterItems,
            'social_links' => $footer->getTranslatedSocialLinks($locale),
            'layout_type' => $footer->layout_type,
            'columns' => $footer->columns,
            'social_links_enabled' => $footer->social_links_enabled,
            'copyright_text' => $footer->getTranslatedCopyrightText($locale),
        ]);
    }

    /**
     * Collect all page IDs referenced by navigation items (recursive).
     *
     * @param array $items
     * @return array<int>
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
     * Get active page IDs for the provided navigation items.
     *
     * @param array $items
     * @return array<int>
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

    protected static function pageHasIsPublicColumn(): bool
    {
        if (static::$pageHasIsPublic !== null) {
            return static::$pageHasIsPublic;
        }

        $pageTable = (new \App\Models\Page())->getTable();
        static::$pageHasIsPublic = \Illuminate\Support\Facades\Schema::hasColumn($pageTable, 'is_public');

        return static::$pageHasIsPublic;
    }

    /**
     * Filter out navigation items that reference inactive pages.
     * Children are filtered recursively; parents remain even if children become empty.
     *
     * @param array $items
     * @param array<int> $activePageIds
     * @return array
     */
    protected function filterItemsByActivePages(array $items, array $activePageIds): array
    {
        $filtered = [];

        foreach ($items as $item) {
            $type = $item['type'] ?? null;
            $pageId = isset($item['page_id']) ? (int) $item['page_id'] : null;

            if ($type === 'page' && (!$pageId || !in_array($pageId, $activePageIds, true))) {
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
     * @unauthenticated
     *
     * @response 200 scenario="Tenant resolved via domain" {"data": {"slug": "stadt-regensburg", "name": "Stadt Regensburg", "domain": "regensburg.example.org", "frontend_base_url": "https://regensburg.example.org", "resolved_by": "domain"}}
     * @response 200 scenario="Tenant resolved via token" {"data": {"slug": "stadt-regensburg", "name": "Stadt Regensburg", "domain": "regensburg.example.org", "frontend_base_url": "https://regensburg.example.org", "resolved_by": "token"}}
     * @response 200 scenario="Default tenant fallback" {"data": {"slug": "default", "name": "Default", "domain": null, "frontend_base_url": null, "resolved_by": "default"}}
     */
    public function tenant(\Illuminate\Http\Request $request): JsonResource
    {
        $tenant = $request->attributes->get('resolved_tenant');
        $resolvedBy = $request->attributes->get('resolved_tenant_by', 'default');
        
        if (!$tenant) {
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
        ]);
    }

    /**
     * Determine the Tenant context from the incoming request.
     *
     * Checks the `tenant` query parameter first, then the `X-Tenant` header; accepts either a numeric id or a slug.
     * If no tenant is found, returns the tenant with slug "default" when present.
     *
     * @param \Illuminate\Http\Request $request The current HTTP request.
     * @return \App\Models\Tenant|null The resolved Tenant model, the tenant with slug "default" if none was specified, or `null` if no default tenant exists.
     */
    protected function resolveTenantFromRequest(\Illuminate\Http\Request $request): ?Tenant
    {
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
     * Update branding configuration
     *
     * Updates the tenant's branding settings. Only provided fields are updated (partial update).
     * Requires admin-api permission and explicit tenant context.
     *
     * @group Admin API - Branding Configuration
     * @authenticated
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
            'primary_color'   => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            'logo_url'        => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
            'accent_color'    => $settings->accent_color,
            'typography_font_family' => $settings->typography_font_family,
            'typography_font_weights' => $settings->typography_font_weights,
            'slider_colors'   => $settings->slider_colors,
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
        ]);
    }
}