{{-- Switch for the "block active" action. Reuses Filament's toggle styles (fi-toggle),
     the first child must stay the knob. The state lives on the server: the action flips it and re-renders this button. --}}
@props([
    'disabled' => false,
    'label' => null,
    'tooltip' => null,
])

@php
    use Filament\Support\View\Components\ToggleComponent;

    $isActive = filter_var($attributes->get('data-active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $hasTooltip = filled($tooltip);
    $wireTarget = $attributes->whereStartsWith(['wire:target', 'wire:click'])->filter(fn ($value): bool => filled($value))->first();
@endphp

<button
    @if ($hasTooltip)
        x-data="{}"
        x-tooltip="{
            content: @js($tooltip),
            theme: $store.theme,
        }"
    @endif
    {{
        $attributes
            ->merge([
                'aria-checked' => $isActive ? 'true' : 'false',
                'disabled' => $disabled,
                'role' => 'switch',
                'type' => 'button',
                'wire:loading.attr' => 'disabled',
                'wire:target' => $wireTarget,
            ], escape: false)
            ->merge([
                'title' => $hasTooltip ? null : $label,
            ], escape: true)
            ->class([
                'fi-toggle',
                ...($isActive
                    ? ['fi-toggle-on', ...\Filament\Support\get_component_color_classes(ToggleComponent::class, 'primary')]
                    : ['fi-toggle-off', ...\Filament\Support\get_component_color_classes(ToggleComponent::class, 'gray')]),
            ])
    }}
>
    <div>
        <div aria-hidden="true"></div>
        <div aria-hidden="true"></div>
    </div>

    @if ($label)
        <span class="fi-sr-only">
            {{ $label }}
        </span>
    @endif
</button>
