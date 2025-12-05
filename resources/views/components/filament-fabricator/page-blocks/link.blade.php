@aware(['page'])

@php
    $text = $text ?? '';
    $url = $url ?? '#';
    $target = $target ?? '_self';
    $style = $style ?? 'primary';
    
    $styleClasses = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700',
        'ghost' => 'bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50',
    ];
    
    $classes = 'inline-block px-6 py-3 rounded-lg font-semibold transition-colors ' . ($styleClasses[$style] ?? $styleClasses['primary']);
@endphp

@if($text && $url)
    <section class="py-8 md:py-12">
        <div class="container mx-auto px-4">
            <a 
                href="{{ $url }}" 
                target="{{ $target }}"
                class="{{ $classes }}"
                @if($target === '_blank')
                    rel="noopener noreferrer"
                @endif
            >
                {{ $text }}
            </a>
        </div>
    </section>
@endif





