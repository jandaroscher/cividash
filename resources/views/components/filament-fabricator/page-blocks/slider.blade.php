@aware(['page'])

@php
    $items = $items ?? [];
@endphp

<section class="py-12 md:py-16">
    <div class="container mx-auto px-4">
        @if(!empty($items) && is_array($items))
            <div class="relative overflow-hidden">
                <div class="flex gap-6 overflow-x-auto snap-x snap-mandatory scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
                    @foreach($items as $item)
                        <div class="flex-shrink-0 w-full md:w-2/3 lg:w-1/2 snap-start">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                @if(isset($item['image']) && $item['image'])
                                    @php
                                        $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($item['image']);
                                    @endphp
                                    <img src="{{ $imageUrl }}" alt="{{ $item['title'] ?? '' }}" class="w-full h-48 object-cover">
                                @endif
                                <div class="p-6">
                                    @if(isset($item['title']) && $item['title'])
                                        <h3 class="text-xl font-semibold mb-2">{{ $item['title'] }}</h3>
                                    @endif
                                    @if(isset($item['description']) && $item['description'])
                                        <p class="text-gray-600 mb-4">{{ $item['description'] }}</p>
                                    @endif
                                    @if(isset($item['link_url']) && $item['link_url'] && isset($item['link_text']) && $item['link_text'])
                                        <a href="{{ $item['link_url'] }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition-colors">
                                            {{ $item['link_text'] }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center text-gray-500 py-8">
                <p>Keine Slider-Items verfügbar.</p>
            </div>
        @endif
    </div>
</section>






