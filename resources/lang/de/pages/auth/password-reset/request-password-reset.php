<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

return [

    'title' => 'Passwort zurücksetzen',

    'heading' => 'Passwort vergessen?',

    'actions' => [

        'login' => [
            'label' => 'zurück zum Login',
        ],

    ],

    'form' => [

        'email' => [
            'label' => 'E-Mail-Adresse',
        ],

        'actions' => [

            'request' => [
                'label' => 'E-Mail zusenden',
            ],

        ],

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Zu viele Versuche.',
            'body' => 'Versuchen Sie es bitte in :seconds Sekunden nochmal.',
        ],

    ],

];
