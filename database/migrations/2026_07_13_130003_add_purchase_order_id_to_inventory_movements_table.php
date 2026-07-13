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
        Schema::table('inventory_movements', function (Blueprint $table) {
            // Terisi kalau movement 'in' ini dipicu penerimaan Purchase
            // Order (PurchaseOrderService::receive()) — traceability ke
            // vendor mana barang ini berasal, lihat CLAUDE.md
            // "Vendor & Supplier". Tetap nullable — movement lain (stock-in
            // manual, konsumsi instalasi, adjustment) tidak terkait PO.
            $table->foreignId('purchase_order_id')->nullable()->after('service_id')
                ->constrained('purchase_orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
