<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang Auto-Antre Dismantle
    |--------------------------------------------------------------------------
    |
    | Berapa bulan sejak services.suspended_at sebelum sebuah Service yang
    | masih suspended otomatis diantrekan untuk dismantle (lihat
    | App\Console\Commands\DismantleQueueOverdueSuspensions dan CLAUDE.md
    | "Dismantle"). Independen dari siklus renewal (services.expired_at) —
    | ini murni "sudah berapa lama TIDAK aktif", bukan "sudah berapa lama
    | tagihan belum dibayar".
    |
    */

    'suspended_months_threshold' => (int) env('DISMANTLE_SUSPENDED_MONTHS_THRESHOLD', 2),

];
