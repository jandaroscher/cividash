<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

return [

    'title' => 'Registrieren',

    'heading' => 'Registrieren',

    'actions' => [

        'login' => [
            'before' => 'oder',
            'label' => 'in Ihr Konto einloggen',
        ],

    ],

    'form' => [

        'email' => [
            'label' => 'E-Mail-Adresse',
        ],

        'name' => [
            'label' => 'Name',
        ],

        'password' => [
            'label' => 'Passwort',
            'validation_attribute' => 'password',
        ],

        'password_confirmation' => [
            'label' => 'Passwort bestätigen',
        ],

        'actions' => [

            'register' => [
                'label' => 'Registrieren',
            ],

        ],

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Zu viele Registrierungsversuche',
            'body' => 'Bitte versuchen Sie es in :seconds Sekunden erneut.',
        ],

    ],

];
