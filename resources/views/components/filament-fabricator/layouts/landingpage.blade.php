@props(['page'])
@once
    @unless (app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/islands.js'])
    @endunless
@endonce
@php
    // Helper function to build locale URLs consistently
    if (!function_exists('buildLocaleUrl')) {
        function buildLocaleUrl($path, $targetLocale) {
            // Normalize: trim leading slashes and remove any existing 'en' prefix
            $normalized = ltrim($path, '/');
            $normalized = preg_replace('#^en(?=/|$)#', '', $normalized);
            
            if ($targetLocale === 'en') {
                // For English: '/en' + (remaining path ? '/'.$remainingPath : '')
                return '/en' . ($normalized ? '/' . $normalized : '');
            } else {
                // For German: '/' + remainingPath (or '/' if empty)
                return '/' . ($normalized ?: '');
            }
        }
    }
    
    $branding = app(\App\Settings\BrandingSettings::class);
    $general = app(\App\Settings\GeneralSettings::class);
    $footer = \App\Models\FooterNavigation::getInstance();
    $header = \App\Models\Navigation::getInstance();
    $logoUrl = $branding->logo_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($branding->logo_url) : null;
    
    // Get current locale first - must be defined before using it
    $path = request()->path();
    $currentLocale = app()->getLocale();
    // Double-check: if path is 'en' or starts with 'en/', force locale to 'en'
    if ($path === 'en' || str_starts_with($path, 'en/')) {
        $currentLocale = 'en';
        app()->setLocale('en');
    } elseif (!str_starts_with($path, 'admin')) {
        // Only set to 'de' if not admin route
        $currentLocale = 'de';
        app()->setLocale('de');
    }
    $alternateLocale = $currentLocale === 'de' ? 'en' : 'de';
    
    // Pre-load all referenced pages to avoid N+1 queries
    $headerNavigationItems = $header->getTranslatedNavigationItems($currentLocale);
    $footerNavigationItems = $footer->getTranslatedFooterNavigationItems($currentLocale);
    
    $pageIds = collect($headerNavigationItems ?? [])
        ->pluck('page_id')
        ->merge(collect($headerNavigationItems ?? [])->pluck('children')->flatten(1)->pluck('page_id'))
        ->merge(collect($footerNavigationItems ?? [])->pluck('page_id'))
        ->filter()
        ->unique()
        ->toArray();
    
    $pagesById = !empty($pageIds) 
        ? \App\Models\Page::whereIn('id', $pageIds)->get()->keyBy('id')
        : collect();
    
    // Build alternate URL using helper function
    $currentPath = request()->path();
    $alternateUrl = buildLocaleUrl($currentPath, $alternateLocale);
    
    // Prepare font loading
    $customFontFile = $branding->typography_custom_font_file 
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($branding->typography_custom_font_file) 
        : null;
    $customFontName = $branding->typography_custom_font_name;
    $fontFamily = $branding->typography_font_family ?? 'Open Sans';
    $fontWeights = $branding->typography_font_weights ?? [400, 600, 700];
    
    // Determine effective font family
    $effectiveFontFamily = $customFontName ?? $fontFamily;
    
    // Build Google Fonts URL if using Google Fonts
    $googleFontUrl = null;
    if (!$customFontFile && $fontFamily) {
        $fontName = str_replace(' ', '+', $fontFamily);
        $weightsParam = implode(';', $fontWeights);
        $googleFontUrl = "https://fonts.googleapis.com/css2?family={$fontName}:wght@{$weightsParam}&display=swap";
    }
    
    // Extract and normalize font format from custom font URL
    $fontFormat = 'opentype'; // default
    if ($customFontFile) {
        $urlPath = parse_url($customFontFile, PHP_URL_PATH);
        $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
        // Map extensions to CSS font format values
        $fontFormat = match($ext) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            default => 'opentype',
        };
    }
@endphp
@if($googleFontUrl)
    <link rel="stylesheet" href="{{ $googleFontUrl }}" id="google-font-{{ str_replace(' ', '-', strtolower($fontFamily)) }}">
@endif
@if($customFontFile && $customFontName)
    <style id="custom-font-{{ str_replace(' ', '-', strtolower($customFontName)) }}">
        @font-face {
            font-family: '{{ addslashes($customFontName) }}';
            src: url('{{ $customFontFile }}') format('{{ $fontFormat }}');
            font-display: swap;
        }
    </style>
@endif
<style>
    :root {
        --primary-color: {{ $branding->primary_color ?? '#1976d2' }};
        --secondary-color: {{ $branding->secondary_color ?? '#0d47a1' }};
        --accent-color: {{ $branding->accent_color ?? $branding->primary_color ?? '#E30613' }};
        --accent-color-dark: {{ $branding->secondary_color ?? '#891F00' }};
        --background-color: {{ $branding->background_color ?? '#F3F4F6' }};
        --card-background-color: {{ $branding->card_background_color ?? '#FFFFFF' }};
        --hero-background-color: {{ $branding->hero_background_color ?? '#111827' }};
        --overlay-background-color: {{ $branding->overlay_background_color ?? 'rgba(0,0,0,0.4)' }};
        --header-background-color: {{ $branding->header_background_color ?? '#FFFFFF' }};
        --footer-background-color: {{ $branding->footer_background_color ?? '#E5E7EB' }};
        --text-primary-color: {{ $branding->text_primary_color ?? '#000000' }};
        --text-secondary-color: {{ $branding->text_secondary_color ?? '#4B5563' }};
        --text-inverse-color: {{ $branding->text_inverse_color ?? '#FFFFFF' }};
        --link-color: {{ $branding->link_color ?? $branding->accent_color ?? $branding->primary_color ?? '#E30613' }};
        --link-hover-color: {{ $branding->link_hover_color ?? $branding->secondary_color ?? '#891F00' }};
        --border-color: {{ $branding->border_color ?? '#D1D5DB' }};
        --divider-color: {{ $branding->divider_color ?? '#E5E7EB' }};
        --shadow-color: {{ $branding->shadow_color ?? '#000000' }};
        --font-family: '{{ addslashes($effectiveFontFamily) }}', ui-sans-serif, system-ui, sans-serif;
        --font-sans: '{{ addslashes($effectiveFontFamily) }}', ui-sans-serif, system-ui, sans-serif;
        @if($branding->typography_font_sizes)
            @foreach($branding->typography_font_sizes as $key => $size)
                --font-size-{{ $key }}: {{ $size }};
            @endforeach
        @else
            --font-size-base: 1rem;
            --font-size-small: 0.875rem;
            --font-size-large: 1.125rem;
            --font-size-h1: 3rem;
            --font-size-h2: 2.25rem;
            --font-size-h3: 1.875rem;
            --font-size-h4: 1.5rem;
            --font-size-h5: 1.25rem;
            --font-size-h6: 1.125rem;
        @endif
        @if($branding->slider_colors)
            --slider-rail-color: {{ $branding->slider_colors['rail'] ?? '#191919' }};
            --slider-handle-color: {{ $branding->slider_colors['handle'] ?? '#E30613' }};
            --slider-handle-border-color: {{ $branding->slider_colors['handleBorder'] ?? '#191919' }};
        @endif
        --nav-text-color: {{ $branding->nav_text_color ?? '#374151' }};
        --nav-text-color-inactive: {{ $branding->nav_text_color_inactive ?? '#9CA3AF' }};
        --nav-hover-color: {{ $branding->nav_hover_color ?? '#FCA5A5' }};
    }
    /* Dropdown menu styles */
    .nav-item.has-dropdown:hover .dropdown-menu {
        display: block;
    }
    .nav-item.has-dropdown .dropdown-menu {
        z-index: 1000;
        margin-top: 0.5rem;
    }
    /* Add gap between nav item and dropdown to prevent closing */
    .nav-item.has-dropdown::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        height: 0.5rem;
    }
    /* Mobile menu styles */
    .mobile-menu {
        display: none;
    }
    .mobile-menu.open {
        display: block !important;
    }
    @media (max-width: 767px) {
        .desktop-nav {
            display: none !important;
        }
        .mobile-menu-toggle {
            display: block;
        }
    }
    @media (min-width: 768px) {
        .mobile-menu-toggle {
            display: none !important;
        }
        .mobile-menu {
            display: none !important;
        }
        .desktop-nav {
            display: flex !important;
        }
        .language-switcher-desktop {
            display: flex !important;
        }
        .language-switcher-mobile {
            display: none !important;
        }
    }
    @media (max-width: 767px) {
        .language-switcher-desktop {
            display: none !important;
        }
        .language-switcher-mobile {
            display: block !important;
        }
    }
</style>
<x-filament-fabricator::layouts.base :title="$page->title">
    <header class="h-30 md:h-[192px] shadow-header" style="background-color: var(--header-background-color, #FFFFFF);">
        <div class="container h-full flex items-center space-x-2">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $general->site_name }}" class="logo md:w-[190px]" style="max-height: 68px;">
            @else
                <h1 class="text-2xl md:text-3xl font-bold" style="color: var(--primary-color, #1976d2);">
                    {{ $general->site_name }}
                </h1>
            @endif
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle md:hidden ml-auto" 
                    onclick="document.getElementById('mobile-menu').classList.toggle('open')"
                    aria-label="{{ __('common.menu.open') }}"
                    style="color: var(--nav-text-color);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            
            <!-- Desktop Navigation -->
            <nav class="desktop-nav ml-auto mt-10 md:mt-0 md:mb-12 flex space-x-2 sm:space-x-6 md:space-x-8 items-end">
                @if(!empty($headerNavigationItems))
                    @foreach($headerNavigationItems as $item)
                        @php
                            // Handle translatable arrays for label and url
                            $url = $item['url'] ?? '#';
                            $label = $item['label'] ?? '';
                            
                            // If url is an array (translatable), get the current locale value
                            if (is_array($url)) {
                                $url = $url[$currentLocale] ?? $url['de'] ?? '#';
                            }
                            
                            // If label is an array (translatable), get the current locale value
                            if (is_array($label)) {
                                $label = $label[$currentLocale] ?? $label['de'] ?? '';
                            }
                            
                            // If type is 'page' and page_id exists, resolve URL from Page model
                            if (($item['type'] ?? 'manual') === 'page' && isset($item['page_id'])) {
                                $navPage = $pagesById->get($item['page_id']);
                                if ($navPage) {
                                    $url = $navPage->getUrl(['locale' => $currentLocale]);
                                    $label = $navPage->getTranslation('title', $currentLocale, false) 
                                        ?: $navPage->getTranslation('title', 'de', false) 
                                        ?: $label;
                                }
                            }
                            $hasChildren = !empty($item['children'] ?? []);
                        @endphp
                        <div class="relative nav-item {{ $hasChildren ? 'has-dropdown' : '' }}">
                            <a href="{{ $url }}" 
                               class="text-sm sm:text-base md:text-2xl md:font-bold transition-colors duration-200" 
                               style="color: var(--nav-text-color);"
                               onmouseover="this.style.color='var(--nav-hover-color)'" 
                               onmouseout="this.style.color='var(--nav-text-color)'">
                                {{ $label }}
                            </a>
                            @if($hasChildren && ($header->dropdown_enabled ?? false))
                                <div class="dropdown-menu absolute top-full left-0 mt-2 bg-white shadow-lg rounded-md py-2 min-w-[200px] hidden">
                                    @foreach($item['children'] as $child)
                                        @php
                                            // getTranslatedNavigationItems() already returns translated strings for children
                                            $childUrl = $child['url'] ?? '#';
                                            $childLabel = $child['label'] ?? '';
                                            
                                            if (($child['type'] ?? 'manual') === 'page' && isset($child['page_id'])) {
                                                $childPage = $pagesById->get($child['page_id']);
                                                if ($childPage) {
                                                    $childUrl = $childPage->getUrl(['locale' => $currentLocale]);
                                                    $childLabel = $childPage->getTranslation('title', $currentLocale, false) 
                                                        ?: $childPage->getTranslation('title', 'de', false) 
                                                        ?: $childLabel;
                                                }
                                            }
                                        @endphp
                                        <a href="{{ $childUrl }}" 
                                           class="block px-4 py-2 text-base transition-colors duration-200"
                                           style="color: var(--link-color, #E30613);"
                                           onmouseover="this.style.backgroundColor='var(--card-background-color, #F5F5F5)'; this.style.color='var(--link-hover-color, #891F00)'" 
                                           onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--link-color, #E30613)'">
                                            {{ $childLabel }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
                @if($header->show_language_switcher)
                    @php
                        $currentPath = request()->path();
                        $deUrl = buildLocaleUrl($currentPath, 'de');
                        $enUrl = buildLocaleUrl($currentPath, 'en');
                    @endphp
                    <select class="language-switcher-mobile cursor-pointer text-sm sm:text-base md:text-2xl md:font-bold relative bg-transparent" 
                            style="color: var(--nav-text-color); border: none; outline: none;"
                            onchange="window.location.href=this.value">
                        <option value="{{ $deUrl }}" {{ $currentLocale === 'de' ? 'selected' : '' }}>DE</option>
                        <option value="{{ $enUrl }}" {{ $currentLocale === 'en' ? 'selected' : '' }}>EN</option>
                    </select>
                    <div class="language-switcher-desktop text-base sm:text-base md:text-2xl md:font-bold relative flex items-center">
                        <button type="button" 
                                onclick="window.location.href='{{ $deUrl }}'"
                                class="hover:opacity-70 transition-colors duration-200"
                                style="color: {{ $currentLocale === 'de' ? 'var(--nav-text-color)' : 'var(--nav-text-color-inactive)' }};">
                            DE
                        </button>
                        <span style="color: var(--nav-text-color-inactive);">&nbsp;/&nbsp;</span>
                        <button type="button"
                                onclick="window.location.href='{{ $enUrl }}'"
                                class="hover:opacity-70 transition-colors duration-200"
                                style="color: {{ $currentLocale === 'en' ? 'var(--nav-text-color)' : 'var(--nav-text-color-inactive)' }};">
                            EN
                        </button>
                    </div>
                @endif
            </nav>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="mobile-menu fixed top-0 left-0 w-full h-full bg-white z-50 pt-20 px-4" style="display: none;">
                <button class="absolute top-4 right-4" 
                        onclick="document.getElementById('mobile-menu').classList.remove('open')"
                        aria-label="{{ __('common.menu.close') }}"
                        style="color: var(--nav-text-color);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <nav class="flex flex-col space-y-4">
                    @if(!empty($header->navigation_items))
                        @foreach($header->navigation_items as $item)
                            @php
                                // Handle translatable arrays for label and url
                                $url = $item['url'] ?? '#';
                                $label = $item['label'] ?? '';
                                
                                // If url is an array (translatable), get the current locale value
                                if (is_array($url)) {
                                    $url = $url[$currentLocale] ?? $url['de'] ?? '#';
                                }
                                
                                // If label is an array (translatable), get the current locale value
                                if (is_array($label)) {
                                    $label = $label[$currentLocale] ?? $label['de'] ?? '';
                                }
                                
                                if (($item['type'] ?? 'manual') === 'page' && isset($item['page_id'])) {
                                    $navPage = $pagesById->get($item['page_id']);
                                    if ($navPage) {
                                        $url = $navPage->getUrl(['locale' => $currentLocale]);
                                        $label = $navPage->getTranslation('title', $currentLocale, false) 
                                            ?: $navPage->getTranslation('title', 'de', false) 
                                            ?: $label;
                                    }
                                }
                                $hasChildren = !empty($item['children'] ?? []);
                            @endphp
                            <div>
                                <a href="{{ $url }}" 
                                   class="text-lg font-bold transition-colors duration-200 block py-2" 
                                   style="color: var(--nav-text-color);"
                                   onclick="document.getElementById('mobile-menu').classList.remove('open')">
                                    {{ $label }}
                                </a>
                                @if($hasChildren && ($header->dropdown_enabled ?? false))
                                    <div class="pl-4 mt-2 space-y-2">
                                        @foreach($item['children'] as $child)
                                            @php
                                                // Handle translatable arrays for child label and url
                                                $childUrl = $child['url'] ?? '#';
                                                $childLabel = $child['label'] ?? '';
                                                
                                                // If childUrl is an array (translatable), get the current locale value
                                                if (is_array($childUrl)) {
                                                    $childUrl = $childUrl[$currentLocale] ?? $childUrl['de'] ?? '#';
                                                }
                                                
                                                // If childLabel is an array (translatable), get the current locale value
                                                if (is_array($childLabel)) {
                                                    $childLabel = $childLabel[$currentLocale] ?? $childLabel['de'] ?? '';
                                                }
                                                
                                                if (($child['type'] ?? 'manual') === 'page' && isset($child['page_id'])) {
                                                    $childPage = $pagesById->get($child['page_id']);
                                                    if ($childPage) {
                                                        $childUrl = $childPage->getUrl(['locale' => $currentLocale]);
                                                        $childLabel = $childPage->getTranslation('title', $currentLocale, false) 
                                                            ?: $childPage->getTranslation('title', 'de', false) 
                                                            ?: $childLabel;
                                                    }
                                                }
                                            @endphp
                                            <a href="{{ $childUrl }}" 
                                               class="text-base transition-colors duration-200 block py-1"
                                               style="color: var(--nav-text-color);"
                                               onclick="document.getElementById('mobile-menu').classList.remove('open')">
                                                {{ $childLabel }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                    @if($header->show_language_switcher)
                        <div class="pt-4 border-t" style="border-color: var(--divider-color);">
                            <div class="flex items-center space-x-2">
                                <span class="text-base font-bold" style="color: var(--nav-text-color);">{{ __('common.language.label') }}</span>
                                <select class="text-base font-bold bg-transparent border-none outline-none" 
                                        style="color: var(--nav-text-color);"
                                        onchange="window.location.href=this.value">
                                    <option value="{{ $deUrl }}" {{ $currentLocale === 'de' ? 'selected' : '' }}>DE</option>
                                    <option value="{{ $enUrl }}" {{ $currentLocale === 'en' ? 'selected' : '' }}>EN</option>
                                </select>
                            </div>
                        </div>
                    @endif
                </nav>
            </div>
        </div>
    </header>

    <main class="overflow-x-hidden pt-7 md:pt-12">
        <x-filament-fabricator::page-blocks :blocks="$page->blocks" />
    </main>

    <footer class="mt-12 pt-9 pb-8 md:pt-11 md:pb-10" style="background-color: var(--footer-background-color, #E5E7EB);">
        <div class="container text-center md:text-left">
            @if(!empty($footerNavigationItems))
                @php
                    $layoutType = $footer->layout_type ?? 'single-row';
                    $columns = $footer->columns ?? 3;
                    $cols = min($columns, 12);
                @endphp
                @if($layoutType === 'multi-column' || $layoutType === 'grid')
                    <style>
                        .footer-grid-{{ $layoutType }} {
                            --cols: {{ $cols }};
                        }
                        @media (min-width: 768px) {
                            .footer-grid-multi-column {
                                grid-template-columns: repeat(var(--cols), minmax(0, 1fr));
                            }
                            .footer-grid-grid {
                                grid-template-columns: repeat(var(--cols), minmax(0, 1fr));
                            }
                        }
                    </style>
                @endif
                <div class="mb-9">
                    @if($layoutType === 'single-row')
                        <div class="md:flex flex-wrap md:justify-center md:space-x-5 xl:space-x-0 xl:grid xl:grid-cols-6 space-y-5 md:space-y-0" style="color: var(--text-primary-color, #000000);">
                            @foreach($footerNavigationItems as $item)
                                @php
                                    // getTranslatedFooterNavigationItems() already returns translated strings
                                    $url = $item['url'] ?? '#';
                                    $label = $item['label'] ?? '';
                                    
                                    // If type is 'page' and page_id exists, resolve URL from Page model
                                    if (($item['type'] ?? 'manual') === 'page' && isset($item['page_id'])) {
                                        $navPage = $pagesById->get($item['page_id']);
                                        if ($navPage) {
                                            $url = $navPage->getUrl(['locale' => $currentLocale]);
                                            $label = $navPage->getTranslation('title', $currentLocale, false) 
                                                ?: $navPage->getTranslation('title', 'de', false) 
                                                ?: $label;
                                        }
                                    }
                                @endphp
                                <a href="{{ $url }}" class="transition-colors duration-200" style="color: var(--link-color, #E30613);" onmouseover="this.style.color='var(--nav-hover-color)'" onmouseout="this.style.color='var(--link-color, #E30613)'">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    @elseif($layoutType === 'multi-column')
                        <div class="grid grid-cols-1 footer-grid-multi-column gap-5" style="color: var(--text-primary-color, #000000);">
                            @foreach($footerNavigationItems as $item)
                                @php
                                    // getTranslatedFooterNavigationItems() already returns translated strings
                                    $url = $item['url'] ?? '#';
                                    $label = $item['label'] ?? '';
                                    if (($item['type'] ?? 'manual') === 'page' && isset($item['page_id'])) {
                                        $navPage = $pagesById->get($item['page_id']);
                                        if ($navPage) {
                                            $url = $navPage->getUrl(['locale' => $currentLocale]);
                                            $label = $navPage->getTranslation('title', $currentLocale, false) 
                                                ?: $navPage->getTranslation('title', 'de', false) 
                                                ?: $label;
                                        }
                                    }
                                @endphp
                                <div>
                                    <a href="{{ $url }}" class="transition-colors duration-200" style="color: var(--link-color, #E30613);" onmouseover="this.style.color='var(--nav-hover-color)'" onmouseout="this.style.color='var(--link-color, #E30613)'">
                                        {{ $label }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @elseif($layoutType === 'grid')
                        <div class="grid grid-cols-1 sm:grid-cols-2 footer-grid-grid gap-5" style="color: var(--text-primary-color, #000000);">
                            @foreach($footerNavigationItems as $item)
                                @php
                                    // getTranslatedFooterNavigationItems() already returns translated strings
                                    $url = $item['url'] ?? '#';
                                    $label = $item['label'] ?? '';
                                    
                                    if (($item['type'] ?? 'manual') === 'page' && isset($item['page_id'])) {
                                        $navPage = $pagesById->get($item['page_id']);
                                        if ($navPage) {
                                            $url = $navPage->getUrl(['locale' => $currentLocale]);
                                            $label = $navPage->getTranslation('title', $currentLocale, false) 
                                                ?: $navPage->getTranslation('title', 'de', false) 
                                                ?: $label;
                                        }
                                    }
                                @endphp
                                <div>
                                    <a href="{{ $url }}" class="transition-colors duration-200" style="color: var(--link-color, #E30613);" onmouseover="this.style.color='var(--nav-hover-color)'" onmouseout="this.style.color='var(--link-color, #E30613)'">
                                        {{ $label }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            @php
                $socialLinks = $footer->getTranslatedSocialLinks($currentLocale);
            @endphp
            @if($footer->social_links_enabled && !empty($socialLinks))
                <div class="flex flex-wrap space-x-5 justify-center md:justify-start xl:justify-end mb-4">
                    @foreach($socialLinks as $social)
                        @if(isset($social['link']) && isset($social['icon']))
                            <a href="{{ $social['link'] }}" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="transition-colors duration-200"
                               style="color: var(--link-color, #E30613);"
                               onmouseover="this.style.color='var(--nav-hover-color)'"
                               onmouseout="this.style.color='var(--link-color, #E30613)'"
                               aria-label="{{ $social['title'] ?? '' }}"
                               title="{{ $social['title'] ?? '' }}">
                                @if(isset($social['icon']))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($social['icon']) }}" alt="{{ $social['title'] ?? '' }}" class="w-6 h-6">
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="mt-4 text-sm text-center md:text-left" style="color: var(--text-secondary-color, #4B5563);">
                @php
                    $copyrightText = $footer->getTranslatedCopyrightText($currentLocale);
                @endphp
                @if($copyrightText)
                    {{ str_replace(['{year}', '{site_name}'], [date('Y'), e($general->site_name)], e($copyrightText)) }}
                @else
                    &copy; {{ date('Y') }} {{ e($general->site_name) }}
                @endif
            </div>
        </div>
    </footer>
</x-filament-fabricator::layouts.base>