<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Renewal Timing
    |--------------------------------------------------------------------------
    |
    | Berapa hari sebelum services.expired_at masing-masing aksi renewal
    | terjadi. 'invoice' = kapan Order Layanan+Receipt perpanjangan otomatis dibuat
    | (juga jadi notifikasi H-5, lihat InvoiceCreatedNotification — tidak
    | ada notifikasi H-5 terpisah). 'h3'/'h1' = kapan reminder WhatsApp
    | susulan dikirim kalau tagihan itu masih belum dibayar. Lihat
    | App\Console\Commands\Renewal* dan CLAUDE.md "Renewal".
    |
    */

    'remind_days_before' => [
        'invoice' => (int) env('RENEWAL_INVOICE_DAYS_BEFORE', 5),
        'h3' => (int) env('RENEWAL_H3_DAYS_BEFORE', 3),
        'h1' => (int) env('RENEWAL_H1_DAYS_BEFORE', 1),
    ],

];
