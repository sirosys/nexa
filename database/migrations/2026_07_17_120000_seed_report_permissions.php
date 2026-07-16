<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * PermissionSeeder::run() selalu full-sync katalog PERMISSIONS terkini
     * (lihat CLAUDE.md "Authorization / Role & Permission") — memanggilnya
     * lagi di sini menambahkan permission reports.view yang baru ke database
     * dev yang sudah ada, tanpa perlu migrate:fresh.
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
