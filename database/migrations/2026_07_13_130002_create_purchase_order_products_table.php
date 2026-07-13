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
        Schema::create('purchase_order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            // Referensi ke inventory_items (BUKAN products langsung) —
            // barang yang dibeli dari vendor selalu barang yang memang
            // sudah dilacak di Inventaris (harus didaftarkan lewat
            // /inventory-items dulu sebelum bisa dipesan ke vendor), lihat
            // CLAUDE.md "Vendor & Supplier".
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            // Snapshot harga beli saat baris ditambahkan, independen dari
            // harga jual products.price.
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            // Nama constraint dipendekkan manual — nama default Laravel
            // ('purchase_order_products_purchase_order_id_inventory_item_id_unique')
            // melebihi batas 64 karakter identifier MySQL.
            $table->unique(['purchase_order_id', 'inventory_item_id'], 'po_products_po_id_item_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_products');
    }
};
