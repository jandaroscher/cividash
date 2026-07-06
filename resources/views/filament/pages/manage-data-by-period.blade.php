<x-filament-panels::page>
    {{-- Selectors live outside the Filament form to prevent updatedInteractsWithForms
         from firing fillFormFromDatabase() when tile metric inputs change.
         Markup mirrors Filament's own table toolbar (x-filament-tables::container
         + header-toolbar row) so this page's filter/search bar looks like every
         other list in the admin, even though it isn't a real Table. --}}
    <x-filament-tables::container>
        <div class="fi-ta-header-toolbar flex flex-col gap-4 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-x-4 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                {{-- Explicit min-width (not a Tailwind class): the panel ships
                     Filament's precompiled CSS, not a custom build that scans
                     this view, so arbitrary utility classes we invent here
                     silently do nothing. Without a width floor, this wrapper's
                     flex-basis:0 child collapses to its padding-only min-content
                     and the selected option text disappears entirely. --}}
                <x-filament::input.wrapper
                    :prefix="__('filament.pages.manage_data_by_period.granularity_label')"
                    style="min-width: 12rem"
                >
                    <x-filament::input.select wire:model.live="selectedGranularity">
                        @foreach ($this->getGranularityOptionsPublic() as $value => $label)
                            <option value="{{ $value }}" @selected($value === $this->selectedGranularity)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper
                    :prefix="__('filament.pages.manage_data_by_period.period_label')"
                    style="min-width: 12rem"
                >
                    <x-filament::input.select wire:model.live="selectedPeriodKey">
                        @foreach ($this->getPeriodKeyOptionsPublic() as $value => $label)
                            <option value="{{ $value }}" @selected($value === $this->selectedPeriodKey)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            {{-- Fixed width (not max-width): as a flex item with no flex-grow,
                 this div otherwise shrinks to its collapsed content size (see
                 the min-width note above) instead of actually filling up to a
                 cap. --}}
            <div class="ms-auto" style="width: 20rem">
                <label class="sr-only">
                    {{ __('filament.pages.manage_data_by_period.search_label') }}
                </label>

                <x-filament::input.wrapper
                    inline-prefix
                    prefix-icon="heroicon-m-magnifying-glass"
                >
                    <x-filament::input
                        type="search"
                        inline-prefix
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('filament.pages.manage_data_by_period.search_placeholder') }}"
                    />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament-tables::container>

    @if ($this->searchHasNoMatches())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament.pages.manage_data_by_period.search_no_results') }}
        </p>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-6">
            {{ __('filament.pages.manage_data_by_period.action_save') }}
        </x-filament::button>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
