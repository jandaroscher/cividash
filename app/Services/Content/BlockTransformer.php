<?php

namespace App\Services\Content;

class BlockTransformer
{
    /**
     * Normalize Filament Builder content blocks into a stable API payload.
     *
     * Supports blocks that provide properties either via a `data` array or directly
     * as top-level fields; each returned block contains `type` and `props`, and
     * entries without a resolvable type are omitted.
     *
     * @param  array  $blocks  Array of input blocks. Accepted shapes:
     *                         - ['type' => 'hero', 'data' => [...]]
     *                         - ['type' => 'hero', 'field1' => 'value1', ...]
     * @return array An indexed array of transformed blocks, each with keys:
     *               - 'type' => string
     *               - 'props' => array
     */
    public function transform(array $blocks): array
    {
        return collect($blocks)->map(function (array $block): ?array {
            $type = $block['type'] ?? $block['handle'] ?? null;

            if (empty($type)) {
                return null;
            }

            // If 'data' key exists, use it; otherwise, extract all non-metadata keys as props
            if (isset($block['data']) && is_array($block['data'])) {
                $props = $block['data'];
            } else {
                // Remove metadata keys and use the rest as props
                $props = array_diff_key($block, array_flip(['type', 'handle', 'id', 'uuid']));
            }

            $isActive = null;

            if (array_key_exists('is_active', $props)) {
                $parsed = filter_var($props['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsed !== null) {
                    $isActive = $parsed;
                }
                unset($props['is_active']);
            } elseif (array_key_exists('is_active', $block)) {
                $parsed = filter_var($block['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsed !== null) {
                    $isActive = $parsed;
                }
            }

            if ($isActive === false) {
                return null;
            }

            return [
                'type' => $type,
                'props' => $props,
            ];
        })->filter()->values()->toArray();
    }
}
