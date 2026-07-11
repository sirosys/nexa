<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing / Invoice Settings
    |--------------------------------------------------------------------------
    |
    | Berapa lama tagihan pendaftaran (Sale) berlaku sebelum auto-cancel
    | (lihat App\Console\Commands\CancelExpiredInvoices, dijadwalkan
    | hourly). Kredensial Xendit ada di config('services.xendit'), bukan
    | di sini — file ini murni untuk aturan bisnis, bukan kredensial.
    |
    */

    'invoice_ttl_days' => (int) env('BILLING_INVOICE_TTL_DAYS', 3),

    /*
    | Metode pembayaran yang diaktifkan di setiap Payment Request Xendit
    | (nilai enum type resmi Xendit Payment Requests API v3). Pelanggan
    | memilih salah satu di halaman checkout hosted Xendit — bukan dipilih
    | staff per-invoice.
    */
    'payment_methods' => ['VIRTUAL_ACCOUNT', 'QR_CODE', 'EWALLET', 'OVER_THE_COUNTER'],

];
