<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_GATEWAY_DRIVER', 'log'),
        // go-whatsapp-web-multidevice diautentikasi lewat HTTP Basic Auth
        // (username/password), bukan Bearer token.
        'url' => env('WA_GATEWAY_URL'),
        'username' => env('WA_GATEWAY_USERNAME'),
        'password' => env('WA_GATEWAY_PASSWORD'),
        'log_channel' => env('WHATSAPP_GATEWAY_LOG_CHANNEL', 'stack'),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    ],

    // Driver 'log' saja untuk sekarang (belum ada perangkat MikroTik
    // sungguhan yang bisa diakses dari dev — lihat CLAUDE.md "Integrasi
    // MikroTik"). Driver sungguhan (REST v7+/API klasik/SSH — protokol
    // belum diputuskan) menyusul begitu perangkat & keputusan protokol ada.
    'mikrotik' => [
        'driver' => env('MIKROTIK_GATEWAY_DRIVER', 'log'),
        'log_channel' => env('MIKROTIK_GATEWAY_LOG_CHANNEL', 'stack'),
    ],

];
