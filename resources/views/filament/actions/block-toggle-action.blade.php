<x-filament-actions::action
    :action="$action"
    dynamic-component="filament::block-toggle"
    :label="$getLabel()"
    :tooltip="$action->getTooltip()"
    :size="$getSize()"
    class="fi-ac-block-toggle-action self-center"
/>
