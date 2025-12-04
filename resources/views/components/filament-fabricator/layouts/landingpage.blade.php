@props(['page'])
@once
    @unless (app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/islands.js'])
    @endunless
@endonce
@php
    $branding = app(\App\Settings\BrandingSettings::class);
    $general = app(\App\Settings\GeneralSettings::class);
    $footer = app(\App\Settings\FooterSettings::class);
    $logoUrl = $branding->logo_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($branding->logo_url) : null;
    
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
@endphp
@if($googleFontUrl)
    <link rel="stylesheet" href="{{ $googleFontUrl }}" id="google-font-{{ str_replace(' ', '-', strtolower($fontFamily)) }}">
@endif
@if($customFontFile && $customFontName)
    <style id="custom-font-{{ str_replace(' ', '-', strtolower($customFontName)) }}">
        @font-face {
            font-family: '{{ addslashes($customFontName) }}';
            src: url('{{ $customFontFile }}') format('{{ pathinfo($customFontFile, PATHINFO_EXTENSION) === 'woff2' ? 'woff2' : (pathinfo($customFontFile, PATHINFO_EXTENSION) === 'woff' ? 'woff' : (pathinfo($customFontFile, PATHINFO_EXTENSION) === 'ttf' ? 'truetype' : 'opentype')) }}');
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
            <nav class="ml-auto mt-10 md:mt-0 md:mb-12 flex space-x-2 sm:space-x-6 md:space-x-8 items-end">
                {{-- Navigation items can be added here or via blocks --}}
            </nav>
        </div>
    </header>

    <main class="overflow-x-hidden pt-7 md:pt-12">
        <x-filament-fabricator::page-blocks :blocks="$page->blocks" />
    </main>

    <footer class="mt-12 pt-9 pb-8 md:pt-11 md:pb-10" style="background-color: var(--footer-background-color, #E5E7EB);">
        <div class="container text-center md:text-left">
            @if(!empty($footer->footer_links))
                <div class="max-w-[287px] md:max-w-none mx-auto mb-9">
                    <div class="md:flex flex-wrap md:justify-center md:space-x-5 xl:space-x-0 xl:grid xl:grid-cols-6 space-y-5 md:space-y-0" style="color: var(--text-primary-color, #000000);">
                        @foreach($footer->footer_links as $link)
                            <div>
                                <a href="{{ $link['url'] ?? '#' }}" class="transition-colors duration-200" style="color: var(--link-color, #E30613);" onmouseover="this.style.color='var(--link-hover-color, #891F00)'" onmouseout="this.style.color='var(--link-color, #E30613)'">
                                    {{ $link['label'] ?? '' }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if(!empty($footer->footer_logos))
                <div class="flex flex-wrap gap-4 items-center mb-4">
                    @foreach($footer->footer_logos as $logo)
                        @if(isset($logo['url']) && isset($logo['image']))
                            <a href="{{ $logo['url'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo['image']) }}" 
                                     alt="{{ $logo['alt'] ?? 'Partner Logo' }}" 
                                     class="h-12" 
                                     style="max-height: 48px;">
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
            @if(!empty($footer->social_links))
                <div class="flex flex-wrap space-x-5 justify-center md:justify-start">
                    @foreach($footer->social_links as $social)
                        @if(isset($social['url']) && isset($social['platform']))
                            <a href="{{ $social['url'] }}" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="transition-colors duration-200"
                               style="color: var(--link-color, #E30613);"
                               onmouseover="this.style.color='var(--link-hover-color, #891F00)'"
                               onmouseout="this.style.color='var(--link-color, #E30613)'"
                               aria-label="{{ $social['platform'] }}">
                                {{ $social['platform'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="mt-4 text-sm text-center md:text-left" style="color: var(--text-secondary-color, #4B5563);">
                &copy; {{ date('Y') }} {{ $general->site_name }}
            </div>
        </div>
    </footer>
</x-filament-fabricator::layouts.base>