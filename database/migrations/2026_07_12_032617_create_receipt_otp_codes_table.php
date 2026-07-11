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
        Schema::create('receipt_otp_codes', function (Blueprint $table) {
            $table->id();
            // Terpisah dari otp_codes (yang terikat ke User utk login) -
            // OTP ini memverifikasi identitas pelanggan yang membuka link
            // publik /pay/{receipt}, bukan proses login. Lihat CLAUDE.md
            // "Billing / Invoice (Xendit)".
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['receipt_id', 'consumed_at', 'expires_at'], 'receipt_otp_codes_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_otp_codes');
    }
};
