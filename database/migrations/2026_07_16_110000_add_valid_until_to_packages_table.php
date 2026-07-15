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
        Schema::table('packages', function (Blueprint $table) {
            // null = unlimited (paket selalu tersedia dipilih selama masih
            // is_starter). Terisi = paket otomatis tidak lagi ditawarkan
            // untuk pendaftaran baru begitu tanggal ini lewat (mis. promo
            // 1-2 bulan) — lihat Package::isAvailable()/scopeAvailable().
            $table->timestamp('valid_until')->nullable()->after('is_starter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
    }
};
