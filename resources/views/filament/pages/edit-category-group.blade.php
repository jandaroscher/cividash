<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    {{-- Form WITHOUT actions at the bottom --}}
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}
    </x-filament-panels::form>

    {{-- Relation managers (Kategorien-Tabelle) --}}
    @php
        $relationManagers = $this->getRelationManagers();
    @endphp

    @if (count($relationManagers))
        <x-filament-panels::resources.relation-managers
            :active-locale="isset($activeLocale) ? $activeLocale : null"
            :active-manager="$this->activeRelationManager ?? array_key_first($relationManagers)"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        />
    @endif

    {{-- Form actions AFTER relation managers --}}
    <x-filament-panels::form
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath() . '.actions'"
    >
        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
