<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\Actions\Action;

trait HasBlockActiveToggleAction
{
    protected static function getBlockActiveToggleAction(): Action
    {
        return Action::make('toggle_block_active')
            ->label(__('filament.blocks.is_active'))
            ->tooltip(function (array $arguments, $component): string {
                $itemKey = $arguments['item'] ?? null;

                if (! $itemKey) {
                    return __('filament.blocks.activate');
                }

                return static::isBlockActiveItem($component, $itemKey)
                    ? __('filament.blocks.deactivate')
                    : __('filament.blocks.activate');
            })
            ->view('filament.actions.block-toggle-action')
            ->extraAttributes(function (array $arguments, $component): array {
                $itemKey = $arguments['item'] ?? null;

                if (! $itemKey) {
                    return ['data-active' => 'true'];
                }

                return [
                    'data-active' => static::isBlockActiveItem($component, $itemKey) ? 'true' : 'false',
                ];
            })
            ->action(function (array $arguments, $component): void {
                $itemKey = $arguments['item'] ?? null;

                if (! $itemKey) {
                    return;
                }

                static::toggleBlockActiveItem($component, $itemKey);
            });
    }

    protected static function isBlockActiveItem($component, string $itemKey): bool
    {
        if (! method_exists($component, 'getRawItemState')) {
            return true;
        }

        $item = $component->getRawItemState($itemKey);

        if (! is_array($item)) {
            return true;
        }

        return static::resolveBlockActive($item);
    }

    protected static function toggleBlockActiveItem($component, string $itemKey): void
    {
        if (! method_exists($component, 'getState') || ! method_exists($component, 'state')) {
            return;
        }

        $state = $component->getState();

        if (! is_array($state) || ! array_key_exists($itemKey, $state)) {
            return;
        }

        $item = $state[$itemKey];

        if (! is_array($item)) {
            return;
        }

        $isActive = static::resolveBlockActive($item);
        $state[$itemKey] = static::setBlockActive($item, ! $isActive);

        $component->state($state);
        if (method_exists($component, 'callAfterStateUpdated')) {
            $component->callAfterStateUpdated();
        }
    }

    protected static function resolveBlockActive(array $item): bool
    {
        if (isset($item['data']) && is_array($item['data']) && array_key_exists('is_active', $item['data'])) {
            return (bool) $item['data']['is_active'];
        }

        if (array_key_exists('is_active', $item)) {
            return (bool) $item['is_active'];
        }

        return true;
    }

    protected static function setBlockActive(array $item, bool $active): array
    {
        if (isset($item['data']) && is_array($item['data'])) {
            $item['data']['is_active'] = $active;
        } else {
            $item['is_active'] = $active;
        }

        return $item;
    }
}
