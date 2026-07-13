<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cocokkan 5 paket yang sudah di-seed (ProductPackageSeeder) ke produk
     * langganannya masing-masing by name — best-effort, no-op aman kalau
     * nama paket/produk sudah diubah manual (tidak match apa-apa). Lihat
     * CLAUDE.md "Product & Package" (base_product_id).
     */
    public function up(): void
    {
        $mapping = [
            'Paket Basic 10 Mbps' => 'Internet Basic 10 Mbps',
            'Paket Standar 20 Mbps' => 'Internet Standar 20 Mbps',
            'Paket Premium 50 Mbps' => 'Internet Premium 50 Mbps',
            'Paket Basic 10 Mbps — Semesteran' => 'Internet Basic 10 Mbps',
            'Promo Tahun Ajaran Baru' => 'Internet Basic 10 Mbps',
        ];

        foreach ($mapping as $packageName => $productName) {
            $productId = DB::table('products')->where('name', $productName)->value('id');

            if ($productId === null) {
                continue;
            }

            DB::table('packages')->where('name', $packageName)->update(['base_product_id' => $productId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('packages')->update(['base_product_id' => null]);
    }
};
