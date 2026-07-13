<?php

use Database\Seeders\SettingSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration (pola sama 2026_07_13_120000_seed_role_permissions.php)
     * — menjalankan SettingSeeder::run() supaya katalog setting ikut ada
     * di database yang migration tabelnya sudah pernah dijalankan.
     * updateOrCreate() di seeder membuat ini idempotent: metadata
     * (label/description/group/type) selalu disinkronkan ulang, tapi
     * `value` yang sudah dikustomisasi staff tidak pernah ditimpa balik.
     */
    public function up(): void
    {
        (new SettingSeeder)->run();
    }

    public function down(): void
    {
        // Data migration — tidak perlu di-reverse (idempotent, aman dijalankan ulang).
    }
};
