@aware(['page'])

@php
    $imageUrl = $image ? \Illuminate\Support\Facades\Storage::disk('public')->url($image) : null;
    $imagePosition = $image_position ?? 'left';
    $isImageLeft = $imagePosition === 'left';
@endphp

<section class="py-12 md:py-16">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            @if($isImageLeft && $imageUrl)
                <div class="order-1">
                    <img src="{{ $imageUrl }}" alt="{{ $image_alt ?? '' }}" loading="lazy" class="w-full h-auto rounded-lg shadow-card">
                </div>
            @endif
            <div class="order-2 {{ $isImageLeft ? '' : 'md:order-1' }}">
                @if(isset($text) && $text)
                    <div class="prose prose-lg max-w-none mb-6 lg:mb-12 [&_a]:transition-colors [&_a]:duration-300">
                        {!! \Illuminate\Support\Str::of($text)->sanitizeHtml() !!}
                    </div>
                @endif
            </div>
            @if(!$isImageLeft && $imageUrl)
                <div class="order-1 md:order-2">
                    <img src="{{ $imageUrl }}" alt="{{ $image_alt ?? '' }}" loading="lazy" class="w-full h-auto rounded-lg shadow-card">
                </div>
            @endif
        </div>
    </div>
</section>



