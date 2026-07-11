<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('status')->default('pending_payment')->after('package_id');
        });

        // Backfill data lama dari kombinasi timestamp yang sudah ada —
        // best-effort, karena riwayat timestamp tidak bisa membedakan
        // pending_payment vs pending_installation (keduanya sama-sama belum
        // punya activated_at). Service baru selanjutnya status-nya dikelola
        // langsung oleh ServiceService, bukan lewat migration ini lagi.
        DB::table('services')->whereNotNull('dismantled_at')->update(['status' => 'dismantled']);

        DB::table('services')
            ->whereNull('dismantled_at')
            ->whereNotNull('canceled_at')
            ->update(['status' => 'canceled']);

        DB::table('services')
            ->whereNull('dismantled_at')
            ->whereNull('canceled_at')
            ->whereNotNull('activated_at')
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhere('expired_at', '>=', now());
            })
            ->update(['status' => 'active']);

        DB::table('services')
            ->whereNull('dismantled_at')
            ->whereNull('canceled_at')
            ->whereNotNull('activated_at')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->update(['status' => 'suspended']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
