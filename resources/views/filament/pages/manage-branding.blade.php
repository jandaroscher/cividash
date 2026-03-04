<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="save">
        <div class="flex justify-end">
            <x-filament::button type="submit" color="primary">
                {{ __('filament.actions.save') }}
            </x-filament::button>
        </div>

        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
