@php
    use Illuminate\Support\Facades\Storage;

    $state = $getState();
    $isLottie = $state && (str_ends_with($state, '.lottie') || str_ends_with($state, '.json'));
    $url = $state ? Storage::disk('public')->url($state) : null;
@endphp

@if($state)
    @if($isLottie)
        {{-- Placeholder, upgraded to a real <dotlottie-wc> element by
             public/js/vendor/dotlottie-wc-init.js once the self-hosted WASM
             URL is configured (must not render the tag directly here, see
             that file's header comment). --}}
        <div
            data-dotlottie-wc
            src="{{ $url }}"
            background-color="transparent"
            speed="1"
            style="width: 40px; height: 40px;"
            loop
            autoplay
        ></div>
    @else
        <img
            src="{{ $url }}"
            alt=""
            style="width: 40px; height: 40px; object-fit: contain;"
        />
    @endif
@endif
