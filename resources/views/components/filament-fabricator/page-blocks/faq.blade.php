@aware(['page'])

@php
    $items = $items ?? [];
@endphp

@if(!empty($items))
    <section class="py-12 md:py-16">
        <div class="container mx-auto px-4">
            <div class="mx-auto space-y-4">
                @foreach($items as $index => $item)
                    @if(isset($item['question']) && isset($item['answer']))
                        <details class="group border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
                            <summary class="cursor-pointer font-semibold text-lg text-gray-900 hover:text-gray-700">
                                {{ $item['question'] }}
                            </summary>
                            <div class="mt-4 prose prose-sm max-w-none text-gray-600">
                                {!! \Illuminate\Support\Str::of($item['answer'])->sanitizeHtml() !!}
                            </div>
                        </details>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif


