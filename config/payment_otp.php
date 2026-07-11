<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment OTP Settings
    |--------------------------------------------------------------------------
    |
    | Terpisah dari config/otp.php (yang khusus OTP login) SENGAJA - supaya
    | mengubah TTL/max attempts OTP login tidak ikut mengubah parameter OTP
    | verifikasi pembayaran di /pay/{receipt}, atau sebaliknya. Lihat
    | CLAUDE.md "Billing / Invoice (Xendit)".
    |
    | NOTE: resources/views/payment/verify-otp.blade.php hardcodes 6 input
    | boxes. Jangan ubah `length` tanpa mengupdate view itu juga.
    |
    */

    'length' => (int) env('PAYMENT_OTP_LENGTH', 6),

    'ttl_minutes' => (int) env('PAYMENT_OTP_TTL_MINUTES', 5),

    'max_attempts' => (int) env('PAYMENT_OTP_MAX_ATTEMPTS', 5),

    'resend_cooldown_seconds' => (int) env('PAYMENT_OTP_RESEND_COOLDOWN_SECONDS', 60),

    /*
    | Sekali terverifikasi, pelanggan tidak diminta kode lagi selama sekian
    | menit (dicek dari receipt_otp_codes.consumed_at, bukan session - tetap
    | valid meski dibuka dari device/browser berbeda dari saat minta kode).
    */
    'verified_grace_minutes' => (int) env('PAYMENT_OTP_VERIFIED_GRACE_MINUTES', 60),

    /*
    | Sama alasan dengan config('otp.queue_connection') - default 'sync'
    | supaya pengiriman OTP tidak nyangkut di tabel jobs tanpa worker.
    */
    'queue_connection' => env('PAYMENT_OTP_QUEUE_CONNECTION', 'sync'),

];
