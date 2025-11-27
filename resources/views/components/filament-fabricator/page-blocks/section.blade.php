@aware(['page'])

@php
    $bgColor = $background_color ?? '#ffffff';
    $hasTitle = isset($title) && $title;
@endphp

<section 
    class="py-12 md:py-16"
    @if($bgColor !== '#ffffff')
        style="background-color: {{ $bgColor }};"
    @endif
>
    <div class="container">
        @if($hasTitle)
            <h2 class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto">{{ $title }}</h2>
        @endif
        <div>
            {{ $slot }}
        </div>
    </div>
</section>


