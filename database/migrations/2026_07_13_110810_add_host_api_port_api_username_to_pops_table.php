<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            // Alamat router untuk integrasi MikroTik (lihat CLAUDE.md
            // "Integrasi MikroTik") — sengaja ditunda sampai protokol
            // koneksi (REST API v7+) dipastikan, bukan ditambah spekulatif
            // sejak modul PoP & Coverage. `token` (sudah ada, encrypted)
            // dipakai sebagai password Basic Auth — TIDAK ada kolom
            // password baru, hindari duplikasi kredensial.
            $table->string('host')->nullable()->after('token');
            $table->unsignedSmallInteger('api_port')->nullable()->after('host');
            $table->string('api_username')->nullable()->after('api_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            $table->dropColumn(['host', 'api_port', 'api_username']);
        });
    }
};
