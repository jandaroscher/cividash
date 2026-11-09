<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

return [
    'columns' => [
        'tile' => [
            'id' => 'Kachel-ID',
            'slug' => 'Kachel-Slug',
            'title' => 'Kachel',
            'description' => 'Beschreibung',
            'time_granularity' => 'Zeitliche Auflösung',
            'hint' => 'Hinweis',
        ],
        'category' => [
            'labels' => 'Kategorien',
            'groups' => 'Kategoriegruppen',
        ],
        'metric' => [
            'key' => 'Metrik-Schlüssel',
            'label' => 'Metrik',
            'unit' => 'Einheit',
            'source' => 'Quelle',
            'source_url' => 'Quellenlink',
            'methodology' => 'Methodik',
            'formula' => 'Berechnung',
        ],
        'value' => [
            'year' => 'Jahr',
            'period_key' => 'Zeitraum',
            'granularity' => 'Granularität',
            'value' => 'Wert',
        ],
    ],

    'errors' => [
        'invalid_query' => 'Ungültige Export-Parameter.',
        'no_valid_fields' => 'Keine gültigen Felder ausgewählt. Bitte mindestens ein Feld aus der Feld-Whitelist angeben oder den Parameter weglassen, um alle Felder zu exportieren.',
        'tile_not_found' => 'Kachel nicht gefunden.',
    ],

    'ui' => [
        'button' => 'Daten herunterladen',
        'button_catalog' => 'Alle Kacheln exportieren',
        'dialog_title' => 'Daten exportieren',
        'dialog_description' => 'Wählen Sie Format und Felder für den Export aus.',
        'format' => 'Format',
        'format_json' => 'JSON',
        'format_csv' => 'CSV (Excel-kompatibel)',
        'fields' => 'Felder',
        'field_groups' => [
            'tile' => 'Kachel-Metadaten',
            'category' => 'Kategorien',
            'metric' => 'Metriken',
            'value' => 'Werte & Jahre',
        ],
        'download' => 'Herunterladen',
        'downloading' => 'Wird vorbereitet …',
        'cancel' => 'Abbrechen',
        'error' => 'Der Export konnte nicht erstellt werden. Bitte versuchen Sie es später erneut.',
    ],
];
