@aware(['page'])

@php
    $imageUrl = $image ? \Illuminate\Support\Facades\Storage::disk('public')->url($image) : null;
@endphp

<section class="relative bg-gray-900 text-white py-20">
    @if($imageUrl)
        <div class="absolute inset-0" style="z-index: var(--z-base);">
            <img src="{{ $imageUrl }}" alt="{{ $image_alt ?? '' }}" class="w-full h-full object-cover opacity-50">
        </div>
    @endif
    <div class="container relative" style="z-index: var(--z-dropdown);">
        <div>
            @if(isset($title) && $title)
                <h1 class="text-4xl lg:text-5xl text-white font-bold mb-6 hyphens-auto">{{ $title }}</h1>
            @endif
            @if(isset($subtitle) && $subtitle)
                <p class="text-xl md:text-2xl mb-8 text-gray-200">{{ $subtitle }}</p>
            @endif
            @if(isset($cta_text) && $cta_text && isset($cta_url) && $cta_url)
                <a href="{{ \Illuminate\Support\Str::startsWith($cta_url, ['http://', 'https://', '/']) ? $cta_url : '/' }}" class="inline-block bg-accent hover:bg-accent-dark text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                    {{ $cta_text }}
                </a>
            @endif
        </div>
    </div>
</section>



