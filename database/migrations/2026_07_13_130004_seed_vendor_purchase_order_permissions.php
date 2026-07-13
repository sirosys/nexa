<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration (pola sama 2026_07_13_120000_seed_role_permissions.php)
     * — menjalankan ulang PermissionSeeder::run() supaya permission modul
     * baru (`vendors`, `purchase_orders`) ikut ter-seed di database yang
     * migration awalnya sudah pernah dijalankan (firstOrCreate +
     * syncPermissions bikin ini idempotent, aman dipanggil berkali-kali).
     * Superadmin otomatis dapat keduanya lewat sync dinamis di
     * PermissionSeeder; role lain sengaja belum diberi akses (superadmin-
     * only untuk iterasi ini), lihat CLAUDE.md "Vendor & Supplier".
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
