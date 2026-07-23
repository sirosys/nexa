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
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            // Plan yang ditagih di Order Layanan ini — kolom langsung (bukan
            // pivot, kardinalitas plan-per-service-order selalu 1), lihat
            // CLAUDE.md "Plan"/"Renewal". plan_price TIDAK dikalikan
            // plan_qty saat dihitung ke total (dianggap TOTAL untuk seluruh
            // durasi plan_qty).
            $table->foreignId('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->decimal('plan_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('plan_qty')->nullable();
            // Snapshot dari packages.is_starter milik package_id yang sedang
            // dipilih — dihitung ulang tiap create/update, lihat CLAUDE.md
            // "Service Order".
            $table->boolean('is_starter')->default(false);
            // Membedakan Order Layanan registrasi vs perpanjangan — lihat
            // CLAUDE.md "Renewal" (dipakai CancelExpiredInvoices supaya
            // tidak salah membatalkan invoice renewal yang sengaja tidak
            // punya expired_at).
            $table->boolean('is_renewal')->default(false);
            $table->decimal('total', 12, 2)->default(0);
            // Turunan SUM(service_order_products.discount), bukan input manual staff.
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('grandtotal', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            // Penanda idempotency reminder H-3/H-1 — lihat CLAUDE.md "Renewal".
            $table->timestamp('renewal_reminder_h3_sent_at')->nullable();
            $table->timestamp('renewal_reminder_h1_sent_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
