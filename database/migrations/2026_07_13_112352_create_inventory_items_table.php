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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            // Satu Product cuma boleh satu definisi inventaris — konsisten
            // "terhubung ke Product" (bukan katalog independen), lihat
            // CLAUDE.md "Inventaris". restrictOnDelete: produk yang sudah
            // punya riwayat stok tidak bisa diam-diam dihapus.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->unique();
            // true = dilacak per unit (inventory_units, mis. modem dengan
            // serial number), false = cukup kuantitas (mis. kabel meteran).
            $table->boolean('is_serialized')->default(false);
            // Cache stok saat ini — dihitung ulang oleh InventoryService
            // setiap ada movement/perubahan status unit, pola sama
            // packages.duration_months (bukan dihitung query SUM live
            // setiap saat).
            $table->unsignedInteger('quantity')->default(0);
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
        Schema::dropIfExists('inventory_items');
    }
};
