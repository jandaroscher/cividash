<?php

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
