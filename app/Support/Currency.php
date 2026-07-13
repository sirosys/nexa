<?php

namespace App\Support;

class Currency
{
    /**
     * Format angka jadi Rupiah ("Rp1.234.567"). Sebelum modul Dashboard,
     * setiap view memformat Rupiah sendiri-sendiri lewat number_format()
     * inline dan tidak konsisten (kadang pakai 2 desimal, kadang tanpa
     * prefix "Rp") — helper ini dipakai Dashboard dan bisa dipakai ulang di
     * modul lain yang butuh format Rupiah konsisten, tanpa retrofit view lama
     * yang sudah berjalan.
     */
    public static function rupiah(float|int $amount, int $decimals = 0): string
    {
        return 'Rp'.number_format((float) $amount, $decimals, ',', '.');
    }
}
