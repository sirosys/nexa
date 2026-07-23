<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Perluas permission role 'finance' jadi kapabel operator harian
     * "Admin/NOC" (2026-07-23) — sebelumnya cuma pegang sales.*, services.*,
     * users.complete-kyc, sekarang ditambah akses dispatch operasional
     * (installations/dismantles/tickets assign-queue-resolve-any) + view
     * sites/coverages/reports/users. Identifier database TETAP
     * 'finance' (dirujuk literal di banyak test & label map) — cuma
     * permission-nya yang diperluas, label UI-nya diganti "Admin/NOC".
     * Lihat CLAUDE.md "Authorization / Role & Permission".
     *
     * PermissionSeeder::run() selalu full-sync katalog terkini, dan
     * menghormati flag `permissions_managed_by_seeder` per role (skip role
     * yang sudah di-custom manual lewat UI /roles) — jadi aman dipanggil
     * ulang di sini tanpa migrate:fresh.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        //
    }
};
