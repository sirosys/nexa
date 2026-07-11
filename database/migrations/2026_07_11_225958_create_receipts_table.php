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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            // Satu Sale = maksimal satu Receipt di iterasi ini (tidak ada
            // retry setelah expired — lihat CLAUDE.md "Billing / Invoice
            // (Xendit)"), jadi unique di sini menegakkan invariant itu.
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            // Null berarti belum berhasil dibuat di Xendit (mis. panggilan
            // HTTP gagal) — sinyal untuk tombol retry manual di UI.
            $table->string('xendit_payment_request_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            // Mirror status mentah dari Xendit (PENDING/REQUIRES_ACTION/
            // SUCCEEDED/FAILED/EXPIRED, dst) — sengaja tidak diterjemahkan
            // ke enum sendiri supaya tidak ada mapping yang bisa basi.
            $table->string('status')->default('PENDING');
            $table->text('checkout_url')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
