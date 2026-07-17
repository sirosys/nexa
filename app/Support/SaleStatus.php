<?php

namespace App\Support;

use App\Models\Sale;

class SaleStatus
{
    /**
     * Status pembayaran Sale diturunkan dari kombinasi timestamp (tidak ada
     * kolom status eksplisit di tabel `sales`, lihat CLAUDE.md "Sales").
     * Diekstrak dari closure yang tadinya terduplikasi di
     * `resources/views/users/show.blade.php` dan
     * `resources/views/reports/finance.blade.php` begitu API
     * customer-facing butuh nilai yang sama (lihat CLAUDE.md
     * "API Customer-Facing") — `class` dipertahankan di sini juga supaya
     * kedua view itu tetap bisa langsung reuse tanpa mengubah markup badge.
     *
     * @return array{key: string, label: string, class: string}
     */
    public static function resolve(Sale $sale): array
    {
        return match (true) {
            $sale->canceled_at !== null => ['key' => 'canceled', 'label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
            $sale->settled_at !== null => ['key' => 'settled', 'label' => 'Lunas', 'class' => 'bg-success-light text-success dark:bg-success/10'],
            $sale->invoiced_at !== null => ['key' => 'invoiced', 'label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
            default => ['key' => 'draft', 'label' => 'Draft', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
        };
    }
}
