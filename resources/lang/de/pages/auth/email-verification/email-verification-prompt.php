<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

return [

    'title' => 'E-Mail-Adresse bestätigen',

    'heading' => 'E-Mail-Adresse bestätigen',

    'actions' => [

        'resend_notification' => [
            'label' => 'Erneut senden',
        ],

    ],

    'messages' => [
        'notification_not_received' => 'Keine E-Mail erhalten?',
        'notification_sent' => 'Wir haben eine E-Mail mit Anweisungen zur Bestätigung des Kontos an :email gesendet.',
    ],

    'notifications' => [

        'notification_resent' => [
            'title' => 'E-Mail erneut gesendet.',
        ],

        'notification_resend_throttled' => [
            'title' => 'Zu viele Versuche.',
            'body' => 'Versuchen Sie es bitte in :seconds Sekunden nochmal.',
        ],

    ],

];
