<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration (pola sama 2026_07_13_130004_seed_vendor_purchase_order_permissions.php)
     * — menjalankan ulang PermissionSeeder::run() supaya permission modul
     * baru (`settings`) ikut ter-seed di database yang migration awalnya
     * sudah pernah dijalankan. Superadmin otomatis dapat keduanya lewat
     * sync dinamis di PermissionSeeder; role lain sengaja belum diberi
     * akses (superadmin-only untuk iterasi ini), lihat CLAUDE.md
     * "System Setting".
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        // Data migration — tidak perlu di-reverse (idempotent, aman dijalankan ulang).
    }
};
