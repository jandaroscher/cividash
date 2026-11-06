{{--
SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG

SPDX-License-Identifier: EUPL-1.2+
--}}

<x-filament-actions::action
    :action="$action"
    dynamic-component="filament::block-toggle"
    :label="$getLabel()"
    :tooltip="$action->getTooltip()"
    :size="$getSize()"
    class="fi-ac-block-toggle-action self-center"
/>
