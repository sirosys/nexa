<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration OTP Settings
    |--------------------------------------------------------------------------
    |
    | Terpisah dari config/otp.php (OTP login) dan config/payment_otp.php
    | (OTP pembayaran) — konsisten pola project ini: mengubah TTL/max
    | attempts salah satu konteks OTP tidak boleh diam-diam ikut mengubah
    | konteks lain (lihat CLAUDE.md "API Customer-Facing"). Dipakai
    | App\Services\RegistrationOtpService untuk memverifikasi kepemilikan
    | nomor telepon SEBELUM akun `User` dibuat — state-nya di Cache (bukan
    | tabel `otp_codes`, yang butuh `user_id` NOT NULL — belum ada User
    | untuk nomor yang baru mendaftar).
    |
    */

    'length' => (int) env('REGISTRATION_OTP_LENGTH', 6),

    'ttl_minutes' => (int) env('REGISTRATION_OTP_TTL_MINUTES', 5),

    'max_attempts' => (int) env('REGISTRATION_OTP_MAX_ATTEMPTS', 5),

    'resend_cooldown_seconds' => (int) env('REGISTRATION_OTP_RESEND_COOLDOWN_SECONDS', 60),

    'queue_connection' => env('REGISTRATION_OTP_QUEUE_CONNECTION', 'sync'),

];
