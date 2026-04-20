@php
    $generalSettings = app(\App\Settings\GeneralSettings::class);
    $faviconUrl = $generalSettings->favicon
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($generalSettings->favicon)
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $meta->title ?? config('app.name', 'Laravel') }}</title>

    @if(isset($meta))
    <meta name="description" content="{{ $meta->description }}">
    <link rel="canonical" href="{{ $meta->canonicalUrl }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="{{ $meta->ogType }}">
    <meta property="og:title" content="{{ $meta->title }}">
    <meta property="og:description" content="{{ $meta->description }}">
    <meta property="og:url" content="{{ $meta->canonicalUrl }}">
    @if($meta->siteName)
    <meta property="og:site_name" content="{{ $meta->siteName }}">
    @endif
    @if($meta->image)
    <meta property="og:image" content="{{ $meta->image }}">
    @endif
    <meta property="og:locale" content="{{ $meta->locale === 'en' ? 'en_US' : 'de_DE' }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta->title }}">
    <meta name="twitter:description" content="{{ $meta->description }}">
    @if($meta->image)
    <meta name="twitter:image" content="{{ $meta->image }}">
    @endif

    {{-- Hreflang --}}
    @foreach($meta->hreflangLinks as $hreflang)
    <link rel="alternate" hreflang="{{ $hreflang['lang'] }}" href="{{ $hreflang['url'] }}">
    @endforeach
    @endif

    @if($faviconUrl)
    <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
