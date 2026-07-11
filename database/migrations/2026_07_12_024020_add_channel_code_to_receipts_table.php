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
        Schema::table('receipts', function (Blueprint $table) {
            // Diisi begitu pelanggan memilih channel di halaman /pay/{receipt}
            // (lihat CLAUDE.md "Billing / Invoice (Xendit)") - null berarti
            // masih menunggu pelanggan memilih (status AWAITING_CHANNEL_SELECTION).
            $table->string('channel_code')->nullable()->after('xendit_payment_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('channel_code');
        });
    }
};
