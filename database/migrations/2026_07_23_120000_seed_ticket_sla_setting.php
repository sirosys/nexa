<?php

use Database\Seeders\SettingSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration (pola sama 2026_07_23_100000_expand_finance_role_to_noc_permissions.php)
     * — `2026_07_13_150001_seed_settings.php` cuma jalan SEKALI, jadi
     * database yang migration itu sudah ter-record tidak akan pernah
     * otomatis kebagian entry `ticket.sla_reminder_hours` yang baru
     * ditambahkan ke katalog `SettingSeeder::SETTINGS` (lihat CLAUDE.md
     * "Ticketing" — pengingat SLA tiket). `updateOrCreate()` di seeder
     * membuat ini aman dipanggil ulang: baris `settings` lain yang sudah
     * ada (dan sudah dikustomisasi staff) tidak ikut tertimpa.
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
