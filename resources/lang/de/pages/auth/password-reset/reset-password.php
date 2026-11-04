<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

return [

    'title' => 'Passwort zurücksetzen',

    'heading' => 'Passwort zurücksetzen',

    'form' => [

        'email' => [
            'label' => 'E-Mail-Adresse',
        ],

        'password' => [
            'label' => 'Passwort',
            'validation_attribute' => 'Passwort',
        ],

        'password_confirmation' => [
            'label' => 'Passwort bestätigen',
        ],

        'actions' => [

            'reset' => [
                'label' => 'Passwort zurücksetzen',
            ],

        ],

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Zu viele Versuche.',
            'body' => 'Bitte in :seconds Sekunden nochmal versuchen.',
        ],

    ],

];
