<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration (pola sama 2026_07_09_005247_assign_roles_from_admin_flag.php)
     * — supaya permission & assignment role selalu ada begitu migration
     * dijalankan (termasuk di database test lewat RefreshDatabase, yang
     * TIDAK menjalankan DatabaseSeeder). Delegasi langsung ke
     * PermissionSeeder::run() (bukan duplikasi ~50 nama permission di
     * sini) supaya cuma ada satu sumber kebenaran untuk katalog & matrix
     * role->permission — lihat CLAUDE.md "Authorization / Role & Permission".
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
