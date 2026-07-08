<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    |
    | NOTE: resources/views/auth/verify-otp.blade.php hardcodes 6 input boxes
    | and App\Http\Requests\Auth\VerifyOtpRequest hardcodes the `digits:6`
    | rule. Do not change `length` without updating both of those too.
    |
    */

    'length' => (int) env('OTP_LENGTH', 6),

    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),

    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),

    /*
    | Queue connection used specifically for SendOtpWhatsappNotification.
    | Defaults to 'sync' so OTP sending never depends on a queue worker
    | being alive locally — QUEUE_CONNECTION itself stays 'database' for
    | everything else. Override to 'database' (or a dedicated connection)
    | once a real WhatsApp gateway HTTP call shouldn't block the request.
    */
    'queue_connection' => env('OTP_QUEUE_CONNECTION', 'sync'),

];
