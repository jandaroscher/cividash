@php
    use Illuminate\Support\Facades\Storage;

    $state = $getState();
    $isLottie = $state && (str_ends_with($state, '.lottie') || str_ends_with($state, '.json'));
    $url = $state ? Storage::disk('public')->url($state) : null;
@endphp

@if($state)
    @if($isLottie)
        <dotlottie-player
            src="{{ $url }}"
            background="transparent"
            speed="1"
            style="width: 40px; height: 40px;"
            loop
            autoplay
        ></dotlottie-player>
    @else
        <img
            src="{{ $url }}"
            alt=""
            style="width: 40px; height: 40px; object-fit: contain;"
        />
    @endif
@endif
