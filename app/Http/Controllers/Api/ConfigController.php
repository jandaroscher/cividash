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

class ConfigController extends Controller
{
    /**
     * Return the application's branding and styling configuration.
     *
     * The resource contains color palette, typography, background and overlay colors, text and link colors,
     * border/divider/shadow colors, slider colors, font sizes, and custom font metadata. Stored file paths
     * for `logo_url` and `typography_custom_font_file` are converted to public URLs when present; otherwise
     * those fields are `null`.
     *
     * @return JsonResource JSON resource containing the following keys: `primary_color`, `secondary_color`,
     * `logo_url`, `accent_color`, `typography_font_family`, `typography_font_weights`, `slider_colors`,
     * `background_color`, `card_background_color`, `hero_background_color`, `overlay_background_color`,
     * `header_background_color`, `footer_background_color`, `text_primary_color`, `text_secondary_color`,
     * `text_inverse_color`, `link_color`, `link_hover_color`, `border_color`, `divider_color`, `shadow_color`,
     * `nav_text_color`, `nav_text_color_inactive`, `nav_hover_color`, `typography_font_sizes`,
     * `typography_custom_font_name`, `typography_custom_font_file`.
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
     * Provide site-wide general configuration values.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource JSON resource with keys:
     *         - `site_name`: the site's display name.
     *         - `site_active`: whether the site is active (`true` or `false`).
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
     * Provide header configuration including translated navigation items and UI toggles.
     *
     * @param \Illuminate\Http\Request $request The HTTP request; may include a `locale` query parameter and tenant identifiers (query `tenant` or `X-Tenant` header) used to resolve context.
     * @return \Illuminate\Http\Resources\Json\JsonResource An object with:
     *  - `navigation_items`: array of navigation items translated to the resolved locale,
     *  - `show_language_switcher`: `true` if the language switcher should be shown, `false` otherwise,
     *  - `dropdown_enabled`: `true` if dropdown navigation is enabled, `false` otherwise.
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

        return new JsonResource([
            'navigation_items' => $navigation->getTranslatedNavigationItems($locale),
            'show_language_switcher' => $navigation->show_language_switcher,
            'dropdown_enabled' => $navigation->dropdown_enabled,
        ]);
    }

    /**
     * Retrieve footer configuration settings as a JSON resource.
     *
     * @param \Illuminate\Http\Request $request The HTTP request (may contain locale parameter)
     * @return \Illuminate\Http\Resources\Json\JsonResource JsonResource containing `footer_navigation_items`, `social_links`, `layout_type`, `columns`, `social_links_enabled`, and `copyright_text`.
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

        return new JsonResource([
            'footer_navigation_items' => $footer->getTranslatedFooterNavigationItems($locale),
            'social_links' => $footer->getTranslatedSocialLinks($locale),
            'layout_type' => $footer->layout_type,
            'columns' => $footer->columns,
            'social_links_enabled' => $footer->social_links_enabled,
            'copyright_text' => $footer->getTranslatedCopyrightText($locale),
        ]);
    }
    
    /**
     * Provide the current tenant's public configuration.
     *
     * If a tenant was attached to the request by middleware it is used; otherwise the tenant with slug "default" is returned.
     *
     * @param \Illuminate\Http\Request $request Request that may contain `resolved_tenant` and `resolved_tenant_by` attributes.
     * @return \Illuminate\Http\Resources\Json\JsonResource JSON resource with keys: `slug`, `name`, `domain`, `frontend_base_url`, and `resolved_by`.
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
     * Update stored branding settings with the validated request data and return the updated branding payload.
     *
     * Only keys present in the validated input are persisted. File path fields (`logo_url` and
     * `typography_custom_font_file`) are converted to public URLs when present; otherwise they are `null`.
     *
     * @param UpdateBrandingRequest $request Validated request containing branding fields to update.
     * @return JsonResource Associative array of branding properties; includes color, typography, background, text, link, navigation, border/divider/shadow values, font sizes and names, and public URLs for `logo_url` and `typography_custom_font_file` when available (otherwise `null`).
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