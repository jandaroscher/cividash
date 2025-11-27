@aware(['page'])

@php
    // Get tile IDs from block data
    // mutateData ensures 'tiles' is always an array of tile IDs
    $tileIds = $tiles ?? [];
    
    // Always load tiles at render time with current locale for correct translations
    // This ensures translations are done based on the current request locale, not the admin locale
    $currentLocale = app()->getLocale();
    
    if (is_array($tileIds) && !empty($tileIds) && is_numeric($tileIds[0] ?? null)) {
        // Load selected tiles
        $tiles = \App\Models\Tile::query()
            ->whereIn('id', $tileIds)
            ->orderBy('position')
            ->get()
            ->map(function ($tile) use ($currentLocale) {
                return [
                    'id' => $tile->id,
                    'title' => $tile->getTranslation('title', $currentLocale, false),
                    'description' => $tile->getTranslation('description', $currentLocale, false),
                    'icon' => $tile->icon,
                ];
            })
            ->toArray();
    } else {
        // If no IDs or empty array, load all tiles (default behavior)
        $tiles = \App\Models\Tile::query()
            ->orderBy('position')
            ->get()
            ->map(function ($tile) use ($currentLocale) {
                return [
                    'id' => $tile->id,
                    'title' => $tile->getTranslation('title', $currentLocale, false),
                    'description' => $tile->getTranslation('description', $currentLocale, false),
                    'icon' => $tile->icon,
                ];
            })
            ->toArray();
    }
@endphp

<section class="py-12 md:py-16">
    <div class="container">
        @if(!empty($tiles) && is_array($tiles))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($tiles as $tile)
                    <div class="shadow-card">
                        <div class="bg-orange-100 py-6 px-4 text-black relative">
                            @if(isset($tile['icon']) && $tile['icon'])
                                <img
                                    src="{{ $tile['icon'] }}"
                                    alt="{{ isset($tile['title']) ? $tile['title'] . ' icon' : 'Icon' }}"
                                    class="w-8 h-8 absolute top-4 right-4"
                                />
                            @endif
                            @if(isset($tile['title']) && $tile['title'])
                                <h3 class="text-3xl font-bold mb-4 hyphens-auto">{{ $tile['title'] }}</h3>
                            @endif
                        </div>
                        <div class="bg-white py-6 px-4">
                            @if(isset($tile['description']) && $tile['description'])
                                <p class="text-sm text-gray-300 hyphens-auto">{{ $tile['description'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center text-gray-500 py-8">
                <p>Keine Tiles verfügbar.</p>
            </div>
        @endif
    </div>
</section>

