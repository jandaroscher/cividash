<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Convert header and footer settings payloads to a translatable structure.
     *
     * Converts header `navigation_items` and footer `footer_navigation_items` and
     * `copyright_text` from string values into associative arrays with language keys
     * (`'de' => originalValue, 'en' => ''`) and updates the corresponding rows in the
     * `settings` table when those records and fields are present.
     */
    public function up(): void
    {
        // Convert header settings navigation_items
        $headerSettings = DB::table('settings')
            ->where('group', 'header')
            ->where('name', 'header')
            ->first();

        if ($headerSettings) {
            $payload = json_decode($headerSettings->payload, true);

            if (isset($payload['navigation_items']) && is_array($payload['navigation_items'])) {
                $convertedItems = array_map(function ($item) {
                    $convertedItem = $item;

                    // Convert label if it's a string
                    if (isset($item['label']) && is_string($item['label'])) {
                        $convertedItem['label'] = ['de' => $item['label'], 'en' => ''];
                    }

                    // Convert url if it's a string
                    if (isset($item['url']) && is_string($item['url'])) {
                        $convertedItem['url'] = ['de' => $item['url'], 'en' => ''];
                    }

                    // Convert children recursively
                    if (isset($item['children']) && is_array($item['children'])) {
                        $convertedItem['children'] = array_map(function ($child) {
                            $convertedChild = $child;
                            if (isset($child['label']) && is_string($child['label'])) {
                                $convertedChild['label'] = ['de' => $child['label'], 'en' => ''];
                            }
                            if (isset($child['url']) && is_string($child['url'])) {
                                $convertedChild['url'] = ['de' => $child['url'], 'en' => ''];
                            }

                            return $convertedChild;
                        }, $item['children']);
                    }

                    return $convertedItem;
                }, $payload['navigation_items']);

                $payload['navigation_items'] = $convertedItems;

                DB::table('settings')
                    ->where('id', $headerSettings->id)
                    ->update(['payload' => json_encode($payload)]);
            }
        }

        // Convert footer settings footer_navigation_items and copyright_text
        $footerSettings = DB::table('settings')
            ->where('group', 'footer')
            ->where('name', 'footer')
            ->first();

        if ($footerSettings) {
            $payload = json_decode($footerSettings->payload, true);
            $updated = false;

            // Convert footer_navigation_items
            if (isset($payload['footer_navigation_items']) && is_array($payload['footer_navigation_items'])) {
                $convertedItems = array_map(function ($item) {
                    $convertedItem = $item;

                    // Convert label if it's a string
                    if (isset($item['label']) && is_string($item['label'])) {
                        $convertedItem['label'] = ['de' => $item['label'], 'en' => ''];
                    }

                    // Convert url if it's a string
                    if (isset($item['url']) && is_string($item['url'])) {
                        $convertedItem['url'] = ['de' => $item['url'], 'en' => ''];
                    }

                    return $convertedItem;
                }, $payload['footer_navigation_items']);

                $payload['footer_navigation_items'] = $convertedItems;
                $updated = true;
            }

            // Convert copyright_text if it's a string
            if (isset($payload['copyright_text']) && is_string($payload['copyright_text'])) {
                $payload['copyright_text'] = ['de' => $payload['copyright_text'], 'en' => ''];
                $updated = true;
            }

            if ($updated) {
                DB::table('settings')
                    ->where('id', $footerSettings->id)
                    ->update(['payload' => json_encode($payload)]);
            }
        }
    }

    /**
     * Reverts header and footer settings payloads from translatable arrays back to plain strings.
     *
     * Converts header `navigation_items` entries (and their nested `children`) by replacing
     * `label` and `url` arrays with their `'de'` values. Converts footer `footer_navigation_items`
     * entries' `label` and `url` arrays to their `'de'` values and collapses `copyright_text`
     * arrays to the `'de'` string. Persists changes to the `settings` table only when the relevant
     * settings rows exist and payload fields are in the expected formats.
     */
    public function down(): void
    {
        // Convert header settings navigation_items back to strings (use 'de' value)
        $headerSettings = DB::table('settings')
            ->where('group', 'header')
            ->where('name', 'header')
            ->first();

        if ($headerSettings) {
            $payload = json_decode($headerSettings->payload, true);

            if (isset($payload['navigation_items']) && is_array($payload['navigation_items'])) {
                $convertedItems = array_map(function ($item) {
                    $convertedItem = $item;

                    // Extract 'de' value from label
                    if (isset($item['label']) && is_array($item['label']) && isset($item['label']['de'])) {
                        $convertedItem['label'] = $item['label']['de'];
                    }

                    // Extract 'de' value from url
                    if (isset($item['url']) && is_array($item['url']) && isset($item['url']['de'])) {
                        $convertedItem['url'] = $item['url']['de'];
                    }

                    // Convert children recursively
                    if (isset($item['children']) && is_array($item['children'])) {
                        $convertedItem['children'] = array_map(function ($child) {
                            $convertedChild = $child;
                            if (isset($child['label']) && is_array($child['label']) && isset($child['label']['de'])) {
                                $convertedChild['label'] = $child['label']['de'];
                            }
                            if (isset($child['url']) && is_array($child['url']) && isset($child['url']['de'])) {
                                $convertedChild['url'] = $child['url']['de'];
                            }

                            return $convertedChild;
                        }, $item['children']);
                    }

                    return $convertedItem;
                }, $payload['navigation_items']);

                $payload['navigation_items'] = $convertedItems;

                DB::table('settings')
                    ->where('id', $headerSettings->id)
                    ->update(['payload' => json_encode($payload)]);
            }
        }

        // Convert footer settings back to strings
        $footerSettings = DB::table('settings')
            ->where('group', 'footer')
            ->where('name', 'footer')
            ->first();

        if ($footerSettings) {
            $payload = json_decode($footerSettings->payload, true);
            $updated = false;

            // Convert footer_navigation_items
            if (isset($payload['footer_navigation_items']) && is_array($payload['footer_navigation_items'])) {
                $convertedItems = array_map(function ($item) {
                    $convertedItem = $item;
                    if (isset($item['label']) && is_array($item['label']) && isset($item['label']['de'])) {
                        $convertedItem['label'] = $item['label']['de'];
                    }
                    if (isset($item['url']) && is_array($item['url']) && isset($item['url']['de'])) {
                        $convertedItem['url'] = $item['url']['de'];
                    }

                    return $convertedItem;
                }, $payload['footer_navigation_items']);

                $payload['footer_navigation_items'] = $convertedItems;
                $updated = true;
            }

            // Convert copyright_text back to string
            if (isset($payload['copyright_text']) && is_array($payload['copyright_text']) && isset($payload['copyright_text']['de'])) {
                $payload['copyright_text'] = $payload['copyright_text']['de'];
                $updated = true;
            }

            if ($updated) {
                DB::table('settings')
                    ->where('id', $footerSettings->id)
                    ->update(['payload' => json_encode($payload)]);
            }
        }
    }
};
