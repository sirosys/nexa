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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            // draft -> ordered -> received, atau draft/ordered -> canceled.
            // Kolom enum eksplisit (bukan dihitung dari timestamp), pola
            // sama services.status.
            $table->string('status')->default('draft');
            // Turunan SUM(purchase_order_products.price * quantity),
            // dihitung ulang server-side tiap create/update — pola sama
            // sales.total, tidak pernah dipercaya dari request client.
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Riwayat transaksi pengadaan, bukan master data katalog —
            // pola sama Sale/Service, bukan Product/Vendor.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
