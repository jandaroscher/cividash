@props([
    'badge' => null,
    'badgeColor' => 'primary',
    'badgeSize' => 'xs',
    'color' => 'primary',
    'disabled' => false,
    'form' => null,
    'formId' => null,
    'href' => null,
    'icon' => null,
    'iconAlias' => null,
    'iconSize' => null,
    'keyBindings' => null,
    'label' => null,
    'labelSrOnly' => false,
    'loadingIndicator' => true,
    'size' => null,
    'spaMode' => null,
    'tag' => 'button',
    'target' => null,
    'tooltip' => null,
    'type' => 'button',
])

@php
    $rawState = $attributes->get('data-active');
    $isActive = filter_var($rawState, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $isActive = $isActive ?? true;

    $buttonClasses = \Illuminate\Support\Arr::toCssClasses([
        'fi-fo-toggle relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent outline-none transition-colors duration-200 ease-in-out focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-70 dark:focus-visible:ring-primary-500 dark:focus-visible:ring-offset-gray-900',
        $isActive ? 'fi-color-custom bg-custom-600' : 'bg-gray-200 dark:bg-gray-700',
        $isActive ? 'fi-color-primary' : null,
    ]);

    $buttonStyles = \Filament\Support\get_color_css_variables(
        $isActive ? 'primary' : 'gray',
        shades: [600],
        alias: $isActive ? 'forms::components.toggle.on' : 'forms::components.toggle.off',
    );

    $hasTooltip = filled($tooltip);
    $wireTarget = $loadingIndicator ? $attributes->whereStartsWith(['wire:target', 'wire:click'])->filter(fn ($value): bool => filled($value))->first() : null;
    $hasLoadingIndicator = filled($wireTarget) || ($type === 'submit' && filled($form));
    $loadingIndicatorTarget = $hasLoadingIndicator ? html_entity_decode($wireTarget ?: $form, ENT_QUOTES) : null;
@endphp

@if ($tag === 'button')
    <button
        @if ($keyBindings || $hasTooltip)
            x-data="{}"
        @endif
        @if ($keyBindings)
            x-bind:id="$id('key-bindings')"
            x-mousetrap.global.{{ collect($keyBindings)->map(fn (string $keyBinding): string => str_replace('+', '-', $keyBinding))->implode('.') }}="document.getElementById($el.id).click()"
        @endif
        @if ($hasTooltip)
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
                    'form' => $formId,
                    'role' => 'switch',
                    'type' => $type,
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => ($hasLoadingIndicator && $loadingIndicatorTarget) ? $loadingIndicatorTarget : null,
                ], escape: false)
                ->merge([
                    'title' => $hasTooltip ? null : $label,
                ], escape: true)
                ->class([$buttonClasses])
                ->style([$buttonStyles])
        }}
    >
        @if ($label)
            <span class="sr-only">
                {{ $label }}
            </span>
        @endif

        <span
            @class([
                'pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                'translate-x-5 rtl:-translate-x-5' => $isActive,
                'translate-x-0' => ! $isActive,
            ])
        >
            @if ($hasLoadingIndicator)
                <x-filament::loading-indicator
                    :attributes="
                        \Filament\Support\prepare_inherited_attributes(
                            new \Illuminate\View\ComponentAttributeBag([
                                'wire:loading.delay.' . config('filament.livewire_loading_delay', 'default') => '',
                                'wire:target' => $loadingIndicatorTarget,
                            ])
                        )->class(['h-3 w-3 text-gray-400'])
                    "
                />
            @endif
        </span>
    </button>
@elseif ($tag === 'a')
    <a
        {{ \Filament\Support\generate_href_html($href, $target === '_blank', $spaMode) }}
        @if ($keyBindings || $hasTooltip)
            x-data="{}"
        @endif
        @if ($keyBindings)
            x-bind:id="$id('key-bindings')"
            x-mousetrap.global.{{ collect($keyBindings)->map(fn (string $keyBinding): string => str_replace('+', '-', $keyBinding))->implode('.') }}="document.getElementById($el.id).click()"
        @endif
        @if ($hasTooltip)
            x-tooltip="{
                content: @js($tooltip),
                theme: $store.theme,
            }"
        @endif
        {{
            $attributes
                ->merge([
                    'aria-checked' => $isActive ? 'true' : 'false',
                    'role' => 'switch',
                ], escape: false)
                ->merge([
                    'title' => $hasTooltip ? null : $label,
                ], escape: true)
                ->class([$buttonClasses])
                ->style([$buttonStyles])
        }}
    >
        @if ($label)
            <span class="sr-only">
                {{ $label }}
            </span>
        @endif

        <span
            @class([
                'pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                'translate-x-5 rtl:-translate-x-5' => $isActive,
                'translate-x-0' => ! $isActive,
            ])
        >
        </span>
    </a>
@endif
