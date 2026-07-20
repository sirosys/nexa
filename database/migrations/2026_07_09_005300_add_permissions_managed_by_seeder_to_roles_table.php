<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timestamp sengaja ditaruh tepat setelah create_permission_tables
     * (bukan tanggal hari ini) — kolom ini harus sudah ada SEBELUM
     * PermissionSeeder::run() pertama kali dipanggil (dari
     * 2026_07_13_120000_seed_role_permissions.php), termasuk di fresh
     * install/RefreshDatabase test suite. Lihat CLAUDE.md "Authorization /
     * Role & Permission" untuk rasional flag ini (opt-out per-role dari
     * sinkronisasi otomatis PermissionSeeder, dipakai modul Role & Permission
     * Management di /roles).
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Default true: setiap role (termasuk yang sudah ada di database
            // dev saat ini) tetap dikelola PermissionSeeder seperti sekarang,
            // sampai superadmin secara eksplisit mengubah permission role itu
            // lewat UI /roles (RoleService::updatePermissions() yang
            // mematikan flag ini untuk role tsb).
            $table->boolean('permissions_managed_by_seeder')->default(true)->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('permissions_managed_by_seeder');
        });
    }
};
