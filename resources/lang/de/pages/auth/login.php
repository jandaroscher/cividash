<?php

return [

    'title' => 'Anmelden',

    'heading' => 'Anmelden',

    'actions' => [

        'register' => [
            'before' => 'oder',
            'label' => 'ein Konto erstellen',
        ],

        'request_password_reset' => [
            'label' => 'Passwort vergessen?',
        ],

    ],

    'form' => [

        'email' => [
            'label' => 'E-Mail-Adresse',
        ],

        'password' => [
            'label' => 'Passwort',
        ],

        'remember' => [
            'label' => 'Angemeldet bleiben',
        ],

        'actions' => [

            'authenticate' => [
                'label' => 'Anmelden',
            ],

        ],

    ],

    'messages' => [

        'failed' => 'Diese Anmeldedaten stimmen nicht mit unseren Aufzeichnungen überein.',

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Zu viele Anmeldeversuche',
            'body' => 'Bitte versuchen Sie es in :seconds Sekunden erneut.',
        ],

    ],

];
