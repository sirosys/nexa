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
    | Channel pembayaran Xendit yang ditawarkan di halaman pilih channel
    | milik NEXA sendiri (GET/POST /pay/{receipt} - lihat
    | App\Http\Controllers\PaymentController). Payment Requests API v3
    | TIDAK punya halaman checkout hosted multi-channel bawaan (dikonfirmasi
    | lewat percobaan ke sandbox: request tanpa channel_code ditolak), jadi
    | NEXA yang menampilkan daftar ini dan mengirim satu channel_code
    | spesifik sesuai pilihan pelanggan. Daftar channel per kategori sengaja
    | dibatasi ke yang paling umum dulu (bukan seluruh channel yang didukung
    | Xendit) - tambah channel baru di sini kalau sudah diuji ke sandbox.
    |
    | Daftar SENGAJA dibatasi ke 3 kategori saja atas permintaan eksplisit
    | user (bukan seluruh channel yang didukung Xendit) - E-Wallet yang
    | sempat ada (OVO/DANA/SHOPEEPAY/LINKAJA, sukses diuji ke sandbox
    | 2026-07-12) dihapus total dari daftar ini.
    |
    | Virtual Account channel_code pakai suffix "_VIRTUAL_ACCOUNT" (mis.
    | BCA_VIRTUAL_ACCOUNT) - BUKAN kode bank polos ("BCA") seperti percobaan
    | pertama yang ditolak Xendit ("API endpoint and method is not supported
    | ... with country 'ID'"). ReceiptService::resolveRequestType() mendeteksi
    | suffix ini untuk mengirim type=REUSABLE_PAYMENT_CODE (bukan PAY) ke
    | Xendit - lihat CLAUDE.md "Billing / Invoice (Xendit)" untuk detail riset.
    */
    'payment_channels' => [
        'qr_code' => [
            'label' => 'QRIS',
            'channels' => [
                ['code' => 'QRIS', 'label' => 'QRIS'],
            ],
        ],
        'virtual_account' => [
            'label' => 'Virtual Account',
            'channels' => [
                ['code' => 'BCA_VIRTUAL_ACCOUNT', 'label' => 'BCA Virtual Account'],
                ['code' => 'MANDIRI_VIRTUAL_ACCOUNT', 'label' => 'Mandiri Virtual Account'],
                ['code' => 'BRI_VIRTUAL_ACCOUNT', 'label' => 'BRI Virtual Account'],
            ],
        ],
        'over_the_counter' => [
            'label' => 'Retail / Gerai',
            'channels' => [
                ['code' => 'ALFAMART', 'label' => 'Alfamart'],
                ['code' => 'INDOMARET', 'label' => 'Indomaret'],
            ],
        ],
    ],

];
