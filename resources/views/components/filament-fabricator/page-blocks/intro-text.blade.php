@aware(['page'])

@php
    // Fabricator passes block data as direct variables, but also via $attributes
    // Try both methods to ensure we get the data
    $imagePath = $image ?? (isset($attributes) ? $attributes->get('image') : null);
    if (is_array($imagePath)) {
        $imagePath = $imagePath[0] ?? null;
    }
    $imageUrl = $imagePath ? \Illuminate\Support\Facades\Storage::disk('public')->url($imagePath) : null;
    
    // Access image_secondary - try both direct variable and $attributes
    $imageSecondaryPath = $image_secondary ?? (isset($attributes) ? $attributes->get('image_secondary') : null);
    if (is_array($imageSecondaryPath)) {
        $imageSecondaryPath = $imageSecondaryPath[0] ?? null;
    }
    $imageSecondaryUrl = $imageSecondaryPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($imageSecondaryPath) : null;
@endphp

<div class="container">
    @if(isset($heading) && $heading)
        <h1 class="text-4xl lg:text-5xl text-black font-bold mb-6 hyphens-auto">{{ $heading }}</h1>
    @endif

    <div class="lg:grid lg:grid-cols-3 lg:gap-10 mb-10 lg:mb-14">
        <div class="col-span-2">
            @if(isset($subheading) && $subheading)
                <h2 class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto">{{ $subheading }}</h2>
            @endif
            
            @if($imageUrl)
                <img 
                    alt="{{ $image_alt ?? '' }}" 
                    class="lg:hidden mb-6 block" 
                    src="{{ $imageUrl }}" 
                    loading="lazy"
                />
            @endif

            @if(isset($text) && $text)
                <div class="prose prose-lg max-w-none [&_p]:text-xl [&_p]:text-black [&_p]:mb-5 [&_p:last-child]:mb-10 [&_a]:transition-colors [&_a]:duration-300">
                    {!! \Illuminate\Support\Str::of($text)->sanitizeHtml() !!}
                </div>
            @endif
        </div>
        
        <div class="col-span-1">
            @if($imageUrl)
                <img 
                    alt="{{ $image_alt ?? '' }}" 
                    class="hidden lg:block mb-10" 
                    src="{{ $imageUrl }}" 
                    loading="lazy"
                />
            @endif
            @if($imageSecondaryUrl)
                <img 
                    alt="{{ $image_secondary_alt ?? '' }}" 
                    class="hidden lg:block" 
                    src="{{ $imageSecondaryUrl }}" 
                    loading="lazy"
                />
            @endif
        </div>
    </div>
</div>



