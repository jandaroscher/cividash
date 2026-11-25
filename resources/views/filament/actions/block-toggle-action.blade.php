{{--
SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG

SPDX-License-Identifier: EUPL-1.2+
--}}

{{-- Same Livewire wiring as Filament's own action buttons (Action::toButtonHtml()). --}}
<x-filament::block-toggle
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag([
            'wire:click' => $action->getLivewireClickHandler(),
            'wire:target' => $action->getLivewireTarget(),
            'x-on:click' => $action->getAlpineClickHandler(),
        ]))
            ->merge($action->getExtraAttributes(), escape: false)
            ->class(['fi-ac-block-toggle-action self-center'])
    "
    :disabled="$action->isDisabled()"
    :label="$action->getLabel()"
    :tooltip="$action->getTooltip()"
/>
