<?php

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration — supaya role & permission selalu ada begitu migration
     * dijalankan (termasuk di database test lewat RefreshDatabase, yang
     * TIDAK menjalankan DatabaseSeeder). Delegasi langsung ke
     * RoleSeeder::run()/PermissionSeeder::run() (bukan duplikasi daftar role
     * & ~55 nama permission di sini) supaya cuma ada satu sumber kebenaran
     * untuk katalog & matrix role->permission — lihat CLAUDE.md
     * "Authorization / Role & Permission". PermissionSeeder::run() selalu
     * mem-full-sync katalog TERKINI (bukan snapshot histori), jadi cukup
     * dipanggil sekali di sini — tidak perlu migration reseed terpisah tiap
     * modul baru menambah permission.
     */
    public function up(): void
    {
        (new RoleSeeder)->run();
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        // Data migration — tidak perlu di-reverse (idempotent, aman dijalankan ulang).
    }
};
