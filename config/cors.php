<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Belum ada aplikasi client customer-facing sungguhan (Android/iOS/Web)
    | yang dibangun (lihat CLAUDE.md "API Customer-Facing") — origin
    | ditambahkan lewat env begitu ada domain client nyata, tanpa perlu
    | deploy ulang kode. Kosong secara default (BUKAN '*') supaya tidak ada
    | origin yang otomatis diizinkan sebelum benar-benar dikonfigurasi.
    |
    */
    'allowed_origins' => array_values(array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer token (Sanctum personal access token), bukan cookie session —
    // tidak butuh kredensial/cookies lintas origin.
    'supports_credentials' => false,

];
