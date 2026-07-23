<?php

namespace App\Support;

use App\Models\ServiceOrder;

class ServiceOrderStatus
{
    /**
     * Status pembayaran Order Layanan diturunkan dari kombinasi timestamp
     * (tidak ada kolom status eksplisit di tabel `service_orders`, lihat
     * CLAUDE.md "Service Order"). Diekstrak dari closure yang tadinya
     * terduplikasi di `resources/views/users/show.blade.php` dan
     * `resources/views/reports/finance.blade.php` begitu API
     * customer-facing butuh nilai yang sama (lihat CLAUDE.md
     * "API Customer-Facing") — `class` dipertahankan di sini juga supaya
     * kedua view itu tetap bisa langsung reuse tanpa mengubah markup badge.
     *
     * @return array{key: string, label: string, class: string}
     */
    public static function resolve(ServiceOrder $serviceOrder): array
    {
        return match (true) {
            $serviceOrder->canceled_at !== null => ['key' => 'canceled', 'label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
            $serviceOrder->settled_at !== null => ['key' => 'settled', 'label' => 'Lunas', 'class' => 'bg-success-light text-success dark:bg-success/10'],
            $serviceOrder->invoiced_at !== null => ['key' => 'invoiced', 'label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
            default => ['key' => 'draft', 'label' => 'Draft', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
        };
    }
}
