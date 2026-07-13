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
            // Kolom enum eksplisit (bukan disimpulkan dari last_online_at),
            // pola sama services.status/purchase_orders.status — diisi
            // oleh scheduled command monitoring:check-pop-status. Lihat
            // CLAUDE.md "Monitoring".
            $table->string('status')->default('unknown')->after('last_online_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
