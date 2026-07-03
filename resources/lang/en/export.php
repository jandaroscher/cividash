<?php

return [
    'columns' => [
        'tile' => [
            'id' => 'Tile ID',
            'slug' => 'Tile slug',
            'title' => 'Tile',
            'description' => 'Description',
            'hint' => 'Hint',
        ],
        'category' => [
            'labels' => 'Categories',
            'groups' => 'Category groups',
        ],
        'metric' => [
            'label' => 'Metric',
            'unit' => 'Unit',
            'source' => 'Source',
            'source_url' => 'Source URL',
            'methodology' => 'Methodology',
            'formula' => 'Calculation',
        ],
        'value' => [
            'year' => 'Year',
            'value' => 'Value',
        ],
    ],

    'errors' => [
        'invalid_query' => 'Invalid export parameters.',
        'no_valid_fields' => 'No valid fields selected. Please pick at least one whitelisted field or omit the parameter to export everything.',
        'tile_not_found' => 'Tile not found.',
    ],

    'ui' => [
        'button' => 'Download data',
        'button_catalog' => 'Export all tiles',
        'dialog_title' => 'Export data',
        'dialog_description' => 'Choose the format and fields for your export.',
        'format' => 'Format',
        'format_json' => 'JSON',
        'format_csv' => 'CSV (Excel-friendly)',
        'fields' => 'Fields',
        'field_groups' => [
            'tile' => 'Tile metadata',
            'category' => 'Categories',
            'metric' => 'Metrics',
            'value' => 'Values & years',
        ],
        'download' => 'Download',
        'downloading' => 'Preparing …',
        'cancel' => 'Cancel',
        'error' => 'The export could not be generated. Please try again later.',
    ],
];
