@aware(['page'])

@php
    $listType = $list_type ?? 'bullet';
    $items = $items ?? [];
    $tag = $listType === 'numbered' ? 'ol' : 'ul';
    $classes = $listType === 'numbered' 
        ? 'list-decimal list-inside space-y-2' 
        : 'list-disc list-inside space-y-2';
@endphp

@if(!empty($items))
    <section class="py-8 md:py-12">
        <div class="container mx-auto px-4">
            <{{ $tag }} class="{{ $classes }}">
                @foreach($items as $item)
                    @if(isset($item['text']) && $item['text'])
                        <li class="text-lg">{{ $item['text'] }}</li>
                    @endif
                @endforeach
            </{{ $tag }}>
        </div>
    </section>
@endif


