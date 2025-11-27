@aware(['page'])

<section class="py-12 md:py-16">
    <div class="container">
        <div class="mx-auto">
            @if(isset($heading) && $heading)
                <h2 class="text-3xl md:text-4xl text-black font-bold mb-6 hyphens-auto">{{ $heading }}</h2>
            @endif
            @if(isset($text) && $text)
                <div class="prose prose-lg max-w-none mb-6 lg:mb-12 [&_a:hover]:text-accent [&_a]:transition-colors [&_a]:duration-300">
                    {!! \Illuminate\Support\Str::of($text)->sanitizeHtml() !!}
                </div>
            @endif
        </div>
    </div>
</section>



