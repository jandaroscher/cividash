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
@endphp
<x-filament-fabricator::layouts.base :title="$page->title">
    <header class="h-30 md:h-[192px] bg-white shadow-header">
        <div class="container h-full flex items-center space-x-2">
            @if($logoUrl)
                <a href="/" class="flex-1">
                    <img src="{{ $logoUrl }}" alt="{{ $general->site_name }}" class="logo md:w-[190px]" style="max-height: 68px;">
                </a>
            @else
                <a href="/" class="flex-1 text-2xl md:text-3xl font-bold" style="color: {{ $branding->primary_color ?? '#1976d2' }};">
                    {{ $general->site_name }}
                </a>
            @endif
            <nav class="ml-auto mt-10 md:mt-0 md:mb-12 flex space-x-2 sm:space-x-6 md:space-x-8 items-end">
                {{-- Navigation items can be added here or via blocks --}}
            </nav>
        </div>
    </header>

    <main class="overflow-x-hidden pt-7 md:pt-12">
        <x-filament-fabricator::page-blocks :blocks="$page->blocks" />
    </main>

    <footer class="bg-gray-200 mt-12 pt-9 pb-8 md:pt-11 md:pb-10">
        <div class="container text-center md:text-left">
            @if(!empty($footer->footer_links))
                <div class="max-w-[287px] md:max-w-none mx-auto mb-9">
                    <div class="md:flex flex-wrap md:justify-center md:space-x-5 xl:space-x-0 xl:grid xl:grid-cols-6 space-y-5 md:space-y-0 text-black">
                        @foreach($footer->footer_links as $link)
                            <div>
                                <a href="{{ $link['url'] ?? '#' }}" class="hover:text-accent transition-colors duration-200">
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
                               class="hover:text-accent transition-colors duration-200"
                               aria-label="{{ $social['platform'] }}">
                                {{ $social['platform'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="mt-4 text-sm text-gray-500 text-center md:text-left">
                &copy; {{ date('Y') }} {{ $general->site_name }}
            </div>
        </div>
    </footer>
</x-filament-fabricator::layouts.base>