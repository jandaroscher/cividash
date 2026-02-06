@php
    use Illuminate\Support\Facades\Storage;

    $state = $getState();
    $isLottie = $state && (str_ends_with($state, '.lottie') || str_ends_with($state, '.json'));
@endphp

@if($state && $isLottie)
<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800 mt-2">
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">{{ __('filament.resources.tile.lottie_preview') }}</p>
    <dotlottie-player
        src="{{ Storage::disk('public')->url($state) }}"
        background="transparent"
        speed="1"
        style="width: 200px; height: 200px;"
        loop
        autoplay
    ></dotlottie-player>
</div>
@elseif($state)
<p class="text-sm text-gray-500 mt-2">{{ __('filament.resources.tile.no_lottie_file') }}</p>
@endif
